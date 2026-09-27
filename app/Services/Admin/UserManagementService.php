<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserManagementService
{
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    public function update(User $user, array $data, User $actor): User
    {
        if ($user->is($actor) && isset($data['role']) && $data['role'] !== 'admin') {
            throw new UnprocessableEntityHttpException('You cannot remove your own admin access.');
        }

        if (
            $user->role === 'admin'
            && isset($data['role'])
            && $data['role'] !== 'admin'
            && User::where('role', 'admin')->count() <= 1
        ) {
            throw new UnprocessableEntityHttpException('The last admin account cannot be demoted.');
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user->fresh();
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new UnprocessableEntityHttpException('You cannot delete your own account.');
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            throw new UnprocessableEntityHttpException('The last admin account cannot be deleted.');
        }

        DB::transaction(fn () => $user->delete());
    }
}
