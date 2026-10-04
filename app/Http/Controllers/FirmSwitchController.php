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

        $request->session()->put('current_tenant_id', $tenant->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Switched to {$tenant->name}.")]);

        return to_route('dashboard');
    }
}
