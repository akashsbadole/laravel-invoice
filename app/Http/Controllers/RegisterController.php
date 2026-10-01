<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChargeTypeSeeder;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        return Inertia::render('auth/register', [
            'industries' => $this->industries(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', Rule::in(array_keys(Industry::all()))],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // While the product is free every new tenant starts on the free plan
        // with no trial clock, so registration never dead-ends at checkout.
        $freePlan = Plan::ensureDefaults()[Plan::FREE_SLUG];

        $user = DB::transaction(function () use ($validated, $freePlan) {
            $tenant = Tenant::create([
                'name' => $validated['business_name'],
                'industry' => $validated['industry'],
                'slug' => $this->uniqueSlug($validated['business_name']),
                'status' => 'active',
                'trial_ends_at' => null,
            ]);

            $settings = new BusinessSetting([
                'business_name' => $validated['business_name'],
                'industry' => $validated['industry'],
            ]);
            $settings->tenant_id = $tenant->id;
            $settings->save();

            // A new tenant starts with the charge catalogue for its trade —
            // otherwise its invoices could carry no charges at all.
            app(ChargeTypeSeeder::class)->seedForTenant($tenant->id, $validated['industry']);

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
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'current_period_ends_at' => null,
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome! Your workspace is ready.')]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @return list<array{key:string,label:string,description:string}>
     */
    protected function industries(): array
    {
        return collect(Industry::all())
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'description' => $config['description'],
            ])
            ->values()
            ->all();
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
