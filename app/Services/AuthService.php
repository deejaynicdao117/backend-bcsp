<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\VerificationCodeMailer;

class AuthService
{
    public function __construct(private readonly VerificationCodeMailer $verificationMailer) {}

    public function register(array $data): array
    {
        $verificationCode = (string) random_int(100000, 999999);
        $user = null;

        DB::transaction(function () use ($data, $verificationCode, &$user): void {
            $user = User::create([
                'name' => $this->fullName($data),
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'resident',
                'email_verification_code_hash' => Hash::make($verificationCode),
                'email_verification_code_expires_at' => now()->addMinutes(10),
            ]);

            $user->residentProfile()->create([
                'last_name' => trim($data['last_name']),
                'first_name' => trim($data['first_name']),
                'middle_name' => filled($data['middle_name'] ?? null)
                    ? trim($data['middle_name'])
                    : null,
                'sex' => $data['sex'],
            ]);
        });

        try {
            $this->verificationMailer->send($user, $verificationCode);
        } catch (\Throwable $exception) {
            $user->delete();

            throw $exception;
        }

        return ['email' => $user->email];
    }

    public function verifyRegistrationCode(string $email, string $code): array
    {
        $user = User::where('email', Str::lower($email))->first();

        if (
            ! $user
            || $user->hasVerifiedEmail()
            || ! $user->email_verification_code_hash
            || ! $user->email_verification_code_expires_at?->isFuture()
            || ! Hash::check($code, $user->email_verification_code_hash)
        ) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or expired. Request a new code and try again.'],
            ]);
        }

        $user->markEmailAsVerified();
        $user->forceFill([
            'email_verification_code_hash' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        $token = $user->createToken('barangay-app')->plainTextToken;

        return [
            'token' => $token,
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    public function resendRegistrationCode(string $email): void
    {
        $user = User::where('email', Str::lower($email))->first();

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        $verificationCode = (string) random_int(100000, 999999);
        $user->forceFill([
            'email_verification_code_hash' => Hash::make($verificationCode),
            'email_verification_code_expires_at' => now()->addMinutes(10),
        ])->save();

        $this->verificationMailer->send($user, $verificationCode);
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', Str::lower(trim($credentials['email'])))->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->role === 'resident' && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Verify your email address before signing in.'],
            ]);
        }

        $token = $user->createToken('barangay-app')->plainTextToken;

        return [
            'token' => $token,
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function fullName(array $data): string
    {
        return implode(' ', array_filter([
            trim($data['first_name']),
            filled($data['middle_name'] ?? null) ? trim($data['middle_name']) : null,
            trim($data['last_name']),
        ]));
    }
}
