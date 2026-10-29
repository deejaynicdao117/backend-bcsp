<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'sex' => ['required', Rule::in(['female', 'male', 'prefer_not_to_say'])],
            'barangay_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $isSelectable = Barangay::query()
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->where('onboarding_status', 'approved')
                        ->where('psgc_code', '<>', 'DEFAULT')
                        ->exists();

                    if (! $isSelectable) {
                        $fail('Select an active Bulacan barangay.');
                    }
                },
            ],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $result = $this->authService->register($validated);

        return response()->json([
            'message' => 'Verification code sent. Check your email to finish creating your resident account.',
            'email' => $result['email'],
        ], 201);
    }

    public function verifyRegistrationCode(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'code' => preg_replace('/\s+/', '', (string) $request->input('code')),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        return response()->json($this->authService->verifyRegistrationCode(
            $validated['email'],
            $validated['code'],
        ));
    }

    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->authService->resendRegistrationCode($validated['email']);

        return response()->json([
            'message' => 'If the resident account is waiting for verification, a new code has been sent.',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        return response()->json($this->authService->login($credentials));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                ...$user->toArray(),
                'barangay' => $user->barangay()->first(['id', 'name', 'municipality', 'province', 'logo_path', 'primary_color']),
            ],
        ]);
    }
}
