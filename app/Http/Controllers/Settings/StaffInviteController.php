<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\StaffInviteMail;
use App\Models\StaffInvite;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class StaffInviteController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        if ($error = $this->subscriptions->staffQuotaError($request->user()->tenant)) {
            return back()->withErrors(['email' => $error]);
        }

        // Already-registered emails are allowed: accepting the invite
        // while logged in with that email joins this firm instead.
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        abort_if($validated['role'] === UserRole::Admin->value && ! $request->user()->isAdmin(), 403);

        // Re-inviting replaces the previous pending invite (new token + expiry).
        // Inviting an already-registered email lets that login join this firm.
        StaffInvite::query()->where('email', $validated['email'])->whereNull('accepted_at')->delete();

        $invite = new StaffInvite([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => StaffInvite::generateToken(),
            'expires_at' => now()->addDays(7),
            'invited_by' => $request->user()->id,
        ]);
        // belongsToTenant hook fills tenant_id from the admin's context.
        $invite->save();

        Mail::to($validated['email'])->send(new StaffInviteMail($invite));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return back();
    }

    public function destroy(Request $request, StaffInvite $invite): RedirectResponse
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        $invite->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation revoked.')]);

        return back();
    }
}
