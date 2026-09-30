<?php

namespace App\Http\Controllers;

use App\Models\StaffInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InviteAcceptController extends Controller
{
    public function create(string $token): Response|RedirectResponse
    {
        $invite = $this->findInvite($token);

        if (! $invite) {
            return Inertia::render('auth/invite-expired');
        }

        if (Auth::check()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/invite-accept', [
            'email' => $invite->email,
            'businessName' => $invite->tenant->businessSetting?->business_name ?? $invite->tenant->name,
            'roleLabel' => $invite->role->label(),
            'token' => $invite->token,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invite = $this->findInvite($token);

        if (! $invite) {
            return to_route('login')->withErrors(['email' => __('This invitation is no longer valid.')]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (User::query()->where('email', $invite->email)->exists()) {
            $invite->update(['accepted_at' => now()]);

            return to_route('login')->with('status', __('An account with this email already exists. Please log in.'));
        }

        $user = new User([
            'name' => $validated['name'],
            'email' => $invite->email,
            'password' => $validated['password'],
            'role' => $invite->role,
            'is_active' => true,
        ]);
        $user->tenant_id = $invite->tenant_id;
        $user->email_verified_at = now();
        $user->save();

        $invite->update(['accepted_at' => now()]);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome aboard!')]);

        return redirect()->intended(route('dashboard'));
    }

    protected function findInvite(string $token): ?StaffInvite
    {
        $invite = StaffInvite::query()->where('token', $token)->first();

        return $invite && $invite->isUsable() ? $invite : null;
    }
}
