<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BarangayController extends Controller
{
    public function locations(): JsonResponse
    {
        $locations = Barangay::query()
            ->where('psgc_code', '!=', 'DEFAULT')
            ->where('is_active', true)
            ->where('onboarding_status', 'approved')
            ->orderBy('province')
            ->orderBy('municipality')
            ->orderBy('name')
            ->get(['id', 'psgc_code', 'name', 'municipality', 'province', 'logo_path', 'primary_color']);

        return response()->json(['data' => $locations]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'province' => ['sometimes', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['all', 'unrequested', 'pending', 'approved', 'declined'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = trim($validated['search'] ?? '');
        $province = trim($validated['province'] ?? '');
        $status = $validated['status'] ?? 'all';
        $query = Barangay::query()->where('psgc_code', '!=', 'DEFAULT');

        if ($province !== '') {
            $query->where('province', $province);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('name', 'like', $term)
                    ->orWhere('municipality', 'like', $term)
                    ->orWhere('province', 'like', $term)
                    ->orWhere('psgc_code', 'like', $term);
            });
        }

        if ($status !== 'all') {
            $query->where('onboarding_status', $status);
        }

        $listingQuery = $query
            ->with('onboardingReviewer:id,name')
            ->orderByRaw("CASE onboarding_status WHEN 'pending' THEN 0 WHEN 'unrequested' THEN 1 WHEN 'approved' THEN 2 WHEN 'declined' THEN 3 ELSE 4 END")
            ->orderBy('municipality')
            ->orderBy('name')
            ->orderBy('id');

        $statusCountsQuery = Barangay::query()->where('psgc_code', '!=', 'DEFAULT');
        if ($province !== '') {
            $statusCountsQuery->where('province', $province);
        }

        $statusCounts = $statusCountsQuery
            ->selectRaw('onboarding_status, COUNT(*) as total')
            ->groupBy('onboarding_status')
            ->pluck('total', 'onboarding_status');

        if ($status === 'all' && $search === '') {
            $perPage = 50;
            $currentPage = (int) ($validated['page'] ?? 1);
            $total = (int) $statusCounts->sum(fn ($count): int => (int) $count);
            $barangayItems = $listingQuery
                ->offset(($currentPage - 1) * $perPage)
                ->limit($perPage)
                ->get();
            $meta = [
                'current_page' => $currentPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ];
        } else {
            $barangays = $listingQuery->paginate(50);
            $barangayItems = $barangays->items();
            $meta = [
                'current_page' => $barangays->currentPage(),
                'last_page' => $barangays->lastPage(),
                'per_page' => $barangays->perPage(),
                'total' => $barangays->total(),
            ];
        }

        return response()->json([
            'data' => $barangayItems,
            'meta' => $meta,
            'status_counts' => [
                'unrequested' => (int) ($statusCounts['unrequested'] ?? 0),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'declined' => (int) ($statusCounts['declined'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'psgc_code' => ['required', 'string', 'max:20', 'unique:barangays,psgc_code'],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('barangays', 'name')
                    ->where('municipality', $request->input('municipality'))
                    ->where('province', $request->input('province', 'Bulacan')),
            ],
            'municipality' => ['required', 'string', 'max:150'],
            'province' => ['sometimes', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'onboarding_status' => ['sometimes', Rule::in(['unrequested', 'pending', 'approved', 'declined'])],
            'logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data['province'] ??= 'Bulacan';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['onboarding_status'] = $data['onboarding_status'] ?? 'approved';

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('barangay-logos', 'public');
        }

        $barangay = Barangay::create($data);

        return response()->json([
            'message' => 'Barangay workspace created.',
            'data' => $barangay->fresh()->load('onboardingReviewer:id,name'),
        ], 201);
    }

    public function save(Request $request, string $psgcCode): JsonResponse
    {
        $barangay = Barangay::where('psgc_code', $psgcCode)->firstOrFail();
        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('barangays', 'name')
                    ->where('municipality', $request->input('municipality', $barangay->municipality))
                    ->where('province', $request->input('province', $barangay->province))
                    ->ignore($barangay->id),
            ],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        unset($data['psgc_code'], $data['municipality'], $data['province']);
        if ($request->hasFile('logo')) {
            if ($barangay->logo_path) {
                Storage::disk('public')->delete($barangay->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('barangay-logos', 'public');
        }

        $barangay->update($data);

        return response()->json([
            'message' => 'Barangay workspace updated.',
            'data' => $barangay->fresh()->load('onboardingReviewer:id,name'),
        ]);
    }

    public function reviewOnboarding(Request $request, Barangay $barangay): JsonResponse
    {
        abort_if($barangay->psgc_code === 'DEFAULT', 422, 'The legacy workspace cannot be reviewed for public registration.');

        $validated = $request->validate([
            'onboarding_status' => ['required', Rule::in(['unrequested', 'pending', 'approved', 'declined'])],
        ]);

        $barangay->forceFill([
            'onboarding_status' => $validated['onboarding_status'],
            'onboarding_reviewed_at' => $validated['onboarding_status'] === 'unrequested' ? null : now(),
            'onboarding_reviewed_by' => $validated['onboarding_status'] === 'unrequested' ? null : $request->user()->id,
        ])->save();

        return response()->json([
            'message' => $validated['onboarding_status'] === 'approved'
                ? $barangay->name.' is verified and available for resident registration.'
                : ($validated['onboarding_status'] === 'declined'
                    ? $barangay->name.' onboarding request was declined.'
                    : ($validated['onboarding_status'] === 'pending'
                        ? $barangay->name.' contact has been recorded for review.'
                        : $barangay->name.' was returned to the official directory without an active request.')),
            'data' => $barangay->fresh()->load('onboardingReviewer:id,name'),
        ]);
    }

    public function workspaceUsers(Barangay $barangay): JsonResponse
    {
        $users = User::withoutGlobalScope('barangay')
            ->where('barangay_id', $barangay->id)
            ->whereIn('role', ['admin', 'staff'])
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'staff' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function createAdministrator(Request $request, Barangay $barangay): JsonResponse
    {
        abort_unless(
            $barangay->onboarding_status === 'approved' && $barangay->is_active,
            409,
            'Verify and activate this barangay before creating workspace accounts.',
        );

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        $data['barangay_id'] = $barangay->id;
        $data['email_verified_at'] = now();
        $user = User::create($data);

        return response()->json([
            'message' => ucfirst($data['role']).' account created for '.$barangay->name.'.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'barangay_id']),
        ], 201);
    }

    public function updateWorkspaceUser(Request $request, Barangay $barangay, int $userId): JsonResponse
    {
        $user = $this->findWorkspaceUser($barangay, $userId);
        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'staff'])],
        ]);

        if (
            $user->role === 'admin'
            && isset($data['role'])
            && $data['role'] !== 'admin'
            && $this->workspaceAdministratorCount($barangay) <= 1
        ) {
            return response()->json(['message' => 'The last barangay administrator cannot be demoted.'], 409);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Barangay team account updated.',
            'data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'barangay_id', 'created_at']),
        ]);
    }

    public function deleteWorkspaceUser(Barangay $barangay, int $userId): JsonResponse
    {
        $user = $this->findWorkspaceUser($barangay, $userId);

        if ($user->role === 'admin' && $this->workspaceAdministratorCount($barangay) <= 1) {
            return response()->json(['message' => 'The last barangay administrator cannot be deleted.'], 409);
        }

        $hasOwnedRecords = DB::table('announcements')->where('author_id', $user->id)->exists()
            || DB::table('document_requests')->where('user_id', $user->id)->exists()
            || DB::table('resident_profiles')->where('user_id', $user->id)->exists()
            || DB::table('household_members')->where('user_id', $user->id)->exists()
            || DB::table('complaints')->where('resident_id', $user->id)->exists()
            || DB::table('disaster_assistance_requests')->where('resident_id', $user->id)->exists()
            || DB::table('portal_notifications')->where('user_id', $user->id)->exists();

        if ($hasOwnedRecords) {
            return response()->json([
                'message' => 'This account has existing barangay records and cannot be deleted without removing that data.',
            ], 409);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Barangay team account deleted.']);
    }

    private function findWorkspaceUser(Barangay $barangay, int $userId): User
    {
        return User::withoutGlobalScope('barangay')
            ->where('barangay_id', $barangay->id)
            ->whereIn('role', ['admin', 'staff'])
            ->findOrFail($userId);
    }

    private function workspaceAdministratorCount(Barangay $barangay): int
    {
        return User::withoutGlobalScope('barangay')
            ->where('barangay_id', $barangay->id)
            ->where('role', 'admin')
            ->count();
    }
}
