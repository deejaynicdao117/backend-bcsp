<?php

namespace App\Http\Middleware;

use App\Support\CurrentBarangay;
use App\Models\Barangay;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentBarangay
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        if (! $user) {
            return $next($request);
        }
        if (! $user->barangay_id) {
            $defaultId = Barangay::where('psgc_code', 'DEFAULT')->value('id');
            abort_unless($defaultId, 403, 'This account is not assigned to a barangay workspace.');
            $user->forceFill(['barangay_id' => $defaultId])->save();
        }

        app(CurrentBarangay::class)->set((int) $user->barangay_id);

        try {
            return $next($request);
        } finally {
            app(CurrentBarangay::class)->set(null);
        }
    }
}