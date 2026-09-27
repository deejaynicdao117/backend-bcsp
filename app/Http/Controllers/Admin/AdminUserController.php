<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(private readonly UserManagementService $userManagementService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff', 'resident'])],
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $this->userManagementService->create($validated),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'staff', 'resident'])],
        ]);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $this->userManagementService->update($user, $validated, $request->user()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->userManagementService->delete($user, $request->user());

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
