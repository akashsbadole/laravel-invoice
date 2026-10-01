<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $trialDays = (int) config('billing.trial_days', 14);

        $user = DB::transaction(function () use ($validated, $trialDays) {
            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'slug' => $this->uniqueSlug($validated['business_name']),
                'status' => 'active',
                'trial_ends_at' => now()->addDays($trialDays),
            ]);

            $settings = new BusinessSetting(['business_name' => $validated['business_name']]);
            $settings->tenant_id = $tenant->id;
            $settings->save();

            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);
            $user->tenant_id = $tenant->id;
            $user->email_verified_at = now();
            $user->save();

            $user->firms()->attach($tenant->id, ['role' => UserRole::Admin->value]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => Plan::ensureDefaults()['starter']->id,
                'status' => 'trialing',
                'trial_ends_at' => $tenant->trial_ends_at,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome! Your 14-day trial has started.')]);

        return redirect()->intended(route('dashboard'));
    }

    protected function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'business';
        $slug = $base;
        $i = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
