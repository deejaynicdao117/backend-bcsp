<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BarangayController extends Controller
{
    public function locations(): JsonResponse
    {
        $locations = Barangay::query()
            ->where('province', 'Bulacan')
            ->where('psgc_code', '!=', 'DEFAULT')
            ->where('is_active', true)
            ->where('onboarding_status', 'approved')
            ->orderBy('municipality')
            ->orderBy('name')
            ->get(['id', 'psgc_code', 'name', 'municipality', 'province', 'logo_path', 'primary_color']);

        return response()->json(['data' => $locations]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Barangay::query()
                ->where('province', 'Bulacan')
                ->where('psgc_code', '!=', 'DEFAULT')
                ->with('onboardingReviewer:id,name')
                ->orderBy('municipality')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'psgc_code' => ['required', 'string', 'max:20', 'unique:barangays,psgc_code'],
            'name' => ['required', 'string', 'max:150'],
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
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

    public function createAdministrator(Request $request, Barangay $barangay): JsonResponse
    {
        abort_unless(
            $barangay->onboarding_status === 'approved' && $barangay->is_active,
            409,
            'Verify and activate this barangay before creating workspace accounts.',
        );

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

    public function importDefaultWorkspace(Barangay $barangay): JsonResponse
    {
        $defaultId = Barangay::where('psgc_code', 'DEFAULT')->value('id');
        abort_unless($defaultId, 404, 'There is no legacy workspace to transfer.');
        abort_if((int) $defaultId === $barangay->id, 422, 'Choose a Bulacan barangay workspace first.');
        abort_unless(DB::table('users')->where('barangay_id', $defaultId)->exists(), 409, 'Legacy data has already been transferred.');

        $tables = [
            'users', 'resident_profiles', 'households', 'complaints', 'announcements',
            'emergency_contacts', 'evacuation_centers', 'disaster_assistance_requests',
            'document_types', 'document_requests', 'portal_notifications', 'activity_logs',
        ];

        DB::transaction(function () use ($tables, $defaultId, $barangay): void {
            DB::table('system_settings')
                ->where('barangay_id', $defaultId)
                ->whereIn('key', DB::table('system_settings')->where('barangay_id', $barangay->id)->select('key'))
                ->delete();

            foreach ($tables as $table) {
                DB::table($table)->where('barangay_id', $defaultId)->update(['barangay_id' => $barangay->id]);
            }

            DB::table('system_settings')->where('barangay_id', $defaultId)->update(['barangay_id' => $barangay->id]);
        });

        return response()->json(['message' => 'Legacy portal data moved into '.$barangay->name.'.']);
    }

}