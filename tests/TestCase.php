<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChargeTypeSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a tenant with an admin user and a live trial subscription,
     * then return the user.
     *
     * Every model is tenant-scoped and most routes run behind
     * EnsureSubscribed, so feature tests need both a real tenant context and
     * a usable plan or every request redirects to billing.
     */
    protected function adminFor(?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();

        $this->subscribe($tenant);
        app(ChargeTypeSeeder::class)->seedForTenant($tenant->id, $tenant->industry);

        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
        $user->firms()->attach($tenant->id, ['role' => UserRole::Admin->value]);

        return $user->refresh();
    }

    protected function subscribe(Tenant $tenant): Subscription
    {
        $plan = Plan::ensureDefaults()['starter'];

        // One subscription per tenant, so calling this for a tenant that
        // already has one must not violate the unique index.
        return Subscription::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'trial_ends_at' => $tenant->trial_ends_at,
            ],
        );
    }

    /**
     * A platform super admin: no tenant, so no tenant scope and no
     * subscription. Used for the /admin panel tests.
     */
    protected function superAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => UserRole::SuperAdmin,
            'tenant_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));

        return $user->refresh();
    }

    /**
     * A tenant staff member holding a specific role.
     */
    protected function userWithRole(UserRole $role, ?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();

        $this->subscribe($tenant);
        app(ChargeTypeSeeder::class)->seedForTenant($tenant->id, $tenant->industry);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
        ]);
        $user->firms()->attach($tenant->id, ['role' => $role->value]);

        return $user->refresh();
    }

    protected function customerFor(User $user, array $attributes = []): Customer
    {
        return Customer::factory()->create(array_merge([
            'tenant_id' => $user->tenant_id,
        ], $attributes));
    }

    /**
     * Tenant-scoped writes outside an HTTP request only get their
     * tenant_id stamped inside a tenant context — see BelongsToTenant.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function inTenant(User $user, callable $callback): mixed
    {
        return Tenant::runInContext($user->tenant_id, $callback);
    }
}
