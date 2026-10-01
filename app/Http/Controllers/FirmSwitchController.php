<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FirmSwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $tenant = Tenant::query()->findOrFail($validated['tenant_id']);
        $user = $request->user();

        abort_unless($tenant->isActive(), 422, 'This firm is not active.');
        abort_unless($user->isMemberOf($tenant->id), 403);

        $role = $user->membershipRole($tenant->id) ?? $user->role;

        // users.tenant_id/role always describe the CURRENT firm, so every
        // policy, scope and validation rule keeps working unchanged.
        $user->forceFill([
            'tenant_id' => $tenant->id,
            'role' => $role,
        ])->save();

        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Switched to {$tenant->name}.")]);

        return to_route('dashboard');
    }
}
