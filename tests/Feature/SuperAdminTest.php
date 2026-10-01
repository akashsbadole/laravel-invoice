<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\PlatformActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The platform admin is a different job from a tenant admin: it manages the
 * application itself, so the panel must be reachable only by a super admin
 * and must see across every tenant.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_super_admin_has_no_tenant(): void
    {
        $admin = $this->superAdmin();

        $this->assertNull($admin->tenant_id);
        $this->assertFalse($admin->hasTenant());
        $this->assertTrue($admin->isSuperAdmin());
    }

    public function test_the_admin_panel_is_reachable_by_a_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/dashboard')
                ->where('isSuperAdmin', true)
            );

        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.plans.index'))->assertOk();
    }

    public function test_a_tenant_admin_cannot_reach_the_admin_panel(): void
    {
        $tenantAdmin = $this->adminFor();

        $this->actingAs($tenantAdmin)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($tenantAdmin)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($tenantAdmin)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($tenantAdmin)->get(route('admin.plans.index'))->assertForbidden();
    }

    public function test_suspending_a_tenant_is_recorded_in_the_platform_audit_trail(): void
    {
        $platform = $this->superAdmin();
        $tenant = $this->adminFor()->tenant;

        $this->actingAs($platform)
            ->post(route('admin.tenants.toggle-status', $tenant))
            ->assertRedirect();

        $log = PlatformActivityLog::query()->latest('id')->firstOrFail();

        $this->assertSame('tenant.suspended', $log->action);
        $this->assertSame($platform->id, $log->user_id);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertStringContainsString('Suspended', $log->description);

        // And the other direction is labelled separately.
        $this->actingAs($platform)
            ->post(route('admin.tenants.toggle-status', $tenant))
            ->assertRedirect();

        $this->assertSame(
            'tenant.reactivated',
            PlatformActivityLog::query()->latest('id')->firstOrFail()->action,
        );
    }

    public function test_impersonation_is_recorded_with_the_super_admin_as_actor(): void
    {
        $platform = $this->superAdmin();
        $tenantAdmin = $this->adminFor();

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenantAdmin->tenant))
            ->assertRedirect();

        $started = PlatformActivityLog::query()
            ->where('action', 'tenant.impersonated')->latest('id')->firstOrFail();

        // The actor is the platform admin, not the tenant user we became.
        $this->assertSame($platform->id, $started->user_id);
        $this->assertSame($tenantAdmin->tenant_id, $started->tenant_id);
        $this->assertSame($tenantAdmin->id, $started->properties['target_user_id']);

        $this->actingAs(auth()->user())
            ->post(route('admin.impersonation.stop'))
            ->assertRedirect();

        $ended = PlatformActivityLog::query()
            ->where('action', 'tenant.impersonation_ended')->latest('id')->firstOrFail();

        $this->assertSame($platform->id, $ended->user_id);
        $this->assertSame(
            $tenantAdmin->name,
            $ended->properties['impersonated_user'],
        );
    }

    public function test_the_audit_trail_is_readable_and_filterable(): void
    {
        $platform = $this->superAdmin();
        $tenant = $this->adminFor()->tenant;

        $this->actingAs($platform)->post(route('admin.tenants.toggle-status', $tenant));

        $this->actingAs($platform)
            ->get(route('admin.activity.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/activity')
                ->has('entries.data', 1)
                ->where('entries.data.0.action', 'tenant.suspended')
                ->where('entries.data.0.action_label', 'Tenant suspended')
                ->where('entries.data.0.actor', $platform->name)
                ->where('entries.data.0.tenant', $tenant->name)
            );

        $this->actingAs($platform)
            ->get(route('admin.activity.index', ['action' => 'user.deactivated']))
            ->assertInertia(fn ($page) => $page->has('entries.data', 0));
    }

    public function test_a_tenant_admin_cannot_read_the_platform_audit_trail(): void
    {
        $this->actingAs($this->adminFor())
            ->get(route('admin.activity.index'))
            ->assertForbidden();
    }

    public function test_a_plan_can_be_edited_from_the_platform(): void
    {
        $platform = $this->superAdmin();
        $starter = Plan::ensureDefaults()['starter'];

        $this->actingAs($platform)->put(route('admin.plans.update', $starter), [
            'name' => 'Starter Plus',
            'price' => 599,
            'max_staff' => 8,
            'max_invoices_per_month' => 250,
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $starter->refresh();

        $this->assertSame('Starter Plus', $starter->name);
        $this->assertEquals(599, $starter->price);
        $this->assertSame(8, $starter->max_staff);
        $this->assertSame(250, $starter->max_invoices_per_month);

        $this->assertSame(
            'plan.updated',
            PlatformActivityLog::query()->latest('id')->firstOrFail()->action,
        );
    }

    public function test_a_blank_invoice_limit_means_unlimited(): void
    {
        $platform = $this->superAdmin();
        $starter = Plan::ensureDefaults()['starter'];

        $this->actingAs($platform)->put(route('admin.plans.update', $starter), [
            'name' => 'Starter',
            'price' => 499,
            'max_staff' => 5,
            'max_invoices_per_month' => null,
            'is_active' => true,
        ])->assertRedirect();

        $this->assertNull($starter->refresh()->max_invoices_per_month);
        $this->assertTrue($starter->allowsUnlimitedInvoices());
    }

    public function test_the_free_plan_cannot_be_made_paid_or_hidden(): void
    {
        $platform = $this->superAdmin();
        $free = Plan::ensureDefaults()[Plan::FREE_SLUG];

        // Paid is refused.
        $this->actingAs($platform)->put(route('admin.plans.update', $free), [
            'name' => $free->name,
            'price' => 100,
            'max_staff' => 5,
            'is_active' => true,
        ])->assertStatus(422);

        // Hidden is refused too — every tenant falls back to it.
        $this->actingAs($platform)->put(route('admin.plans.update', $free), [
            'name' => $free->name,
            'price' => 0,
            'max_staff' => 5,
        ])->assertStatus(422);

        $this->assertEquals(0, $free->refresh()->price);
        $this->assertTrue($free->is_active);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_the_dashboard_counts_every_tenant(): void
    {
        // A migration backfills one default tenant, so measure the delta
        // rather than an absolute number.
        $baseline = Tenant::query()->count();

        $this->adminFor();
        $this->adminFor();
        $platform = $this->superAdmin();
        Tenant::factory()->create(['status' => 'suspended']);

        $this->actingAs($platform)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.tenants', $baseline + 3)
                ->where('stats.suspended_tenants', 1)
                ->where('stats.super_admins', 1)
                // Two tenant admins plus the platform account.
                ->where('stats.users', 3)
            );
    }

    public function test_the_tenant_list_searches_across_tenants(): void
    {
        $this->adminFor(Tenant::factory()->create(['name' => 'Bill Smith Jewellers']));
        $this->adminFor(Tenant::factory()->create(['name' => 'Tile House']));

        $this->actingAs($this->superAdmin())
            ->get(route('admin.tenants.index', ['search' => 'Jewellers']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tenants.data', 1)
                ->where('tenants.data.0.name', 'Bill Smith Jewellers')
            );
    }

    public function test_a_tenant_can_be_suspended_and_reactivated(): void
    {
        $tenant = $this->adminFor()->tenant;

        $this->actingAs($this->superAdmin())
            ->post(route('admin.tenants.toggle-status', $tenant))
            ->assertRedirect();

        $this->assertSame('suspended', $tenant->refresh()->status);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.tenants.toggle-status', $tenant))
            ->assertRedirect();

        $this->assertSame('active', $tenant->refresh()->status);
    }

    public function test_a_subscription_can_be_changed_from_the_panel(): void
    {
        $tenant = $this->adminFor()->tenant;
        $professional = Plan::ensureDefaults()['professional'];

        $this->actingAs($this->superAdmin())
            ->put(route('admin.tenants.subscription.update', $tenant), [
                'plan_id' => $professional->id,
                'status' => 'past_due',
            ])
            ->assertRedirect();

        $subscription = $tenant->refresh()->subscription;

        $this->assertSame($professional->id, $subscription->plan_id);
        $this->assertSame('past_due', $subscription->status);
    }

    public function test_a_missing_plan_is_rejected(): void
    {
        $tenant = $this->adminFor()->tenant;

        $this->actingAs($this->superAdmin())
            ->put(route('admin.tenants.subscription.update', $tenant), [
                'plan_id' => 99999,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('plan_id');
    }

    public function test_a_user_can_be_disabled_from_the_panel(): void
    {
        $staff = $this->userWithRole(UserRole::InvoiceCreator);

        $this->actingAs($this->superAdmin())
            ->post(route('admin.users.toggle-active', $staff))
            ->assertRedirect();

        $this->assertFalse($staff->refresh()->is_active);
    }

    public function test_a_super_admin_cannot_be_disabled_from_the_panel(): void
    {
        $first = $this->superAdmin();
        $second = $this->superAdmin();

        $this->actingAs($first)
            ->post(route('admin.users.toggle-active', $second))
            ->assertStatus(422);

        $this->assertTrue($second->refresh()->is_active);
    }

    public function test_the_user_list_spans_tenants(): void
    {
        $staff = $this->userWithRole(UserRole::InvoiceCreator);
        $manager = $this->userWithRole(UserRole::Manager);
        $platform = $this->superAdmin();

        $this->actingAs($platform)
            ->get(route('admin.users.index', ['search' => '']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Search by each seeded staff member's email so the list is
                // exactly the rows this test created.
                ->where(
                    'users.data',
                    fn ($rows) => collect($rows)->pluck('id')
                        ->sort()
                        ->values()
                        ->all() === collect([$staff->id, $manager->id, $platform->id])->sort()->values()->all(),
                )
                ->where(
                    'users.data',
                    fn ($rows) => collect($rows)
                        ->filter(fn (array $row) => $row['tenant'] !== null)
                        ->count() === 2,
                )
                ->where(
                    'users.data',
                    fn ($rows) => collect($rows)
                        ->contains(fn (array $row) => $row['id'] === $platform->id && $row['tenant'] === null),
                )
            );
    }

    public function test_impersonating_a_tenant_switches_into_their_workspace(): void
    {
        $platform = $this->superAdmin();
        $tenantAdmin = $this->adminFor();

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenantAdmin->tenant))
            ->assertRedirect(route('dashboard'));

        // Signed in as the tenant's own admin, inside their tenant.
        $this->assertSame($tenantAdmin->id, auth()->id());
        $this->assertSame($tenantAdmin->tenant_id, Tenant::currentId());
        $this->assertTrue(Tenant::isImpersonating());

        $this->actingAs(auth()->user())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('impersonating.tenant_name', $tenantAdmin->tenant->name)
                ->where('impersonating.user_name', $tenantAdmin->name)
            );
    }

    public function test_stopping_impersonation_restores_the_super_admin(): void
    {
        $platform = $this->superAdmin();
        $tenantAdmin = $this->adminFor();

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenantAdmin->tenant))
            ->assertRedirect();

        $this->actingAs(auth()->user())
            ->post(route('admin.impersonation.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($platform->id, auth()->id());
        $this->assertFalse(Tenant::isImpersonating());
    }

    public function test_a_suspended_tenant_cannot_be_impersonated(): void
    {
        $platform = $this->superAdmin();
        $tenant = $this->adminFor()->tenant;
        $tenant->update(['status' => 'suspended']);

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenant))
            ->assertStatus(422);

        $this->assertSame($platform->id, auth()->id());
    }

    public function test_a_tenant_without_active_staff_cannot_be_impersonated(): void
    {
        $platform = $this->superAdmin();
        $tenant = Tenant::factory()->create();

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenant))
            ->assertStatus(422);
    }

    public function test_impersonation_prefers_an_admin_over_other_staff(): void
    {
        $platform = $this->superAdmin();
        $tenantAdmin = $this->adminFor();
        $this->userWithRole(UserRole::Viewer, $tenantAdmin->tenant);

        $this->actingAs($platform)
            ->post(route('admin.tenants.impersonate', $tenantAdmin->tenant))
            ->assertRedirect();

        $this->assertSame($tenantAdmin->id, auth()->id());
    }

    public function test_a_super_admin_landing_on_the_admin_panel_after_login(): void
    {
        $admin = $this->superAdmin([
            'email' => 'root@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post(route('login'), [
            'email' => 'root@example.test',
            'password' => 'secret1234',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame($admin->id, auth()->id());
    }

    public function test_the_super_admin_command_creates_a_tenant_less_account(): void
    {
        $this->artisan('app:make-super-admin', [
            'email' => 'ops@example.test',
            '--name' => 'Ops',
            '--password' => 'secret1234',
        ])->assertSuccessful();

        $user = User::query()->withoutGlobalScopes()->where('email', 'ops@example.test')->firstOrFail();

        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertNull($user->tenant_id);
        $this->assertTrue($user->is_active);
    }

    public function test_the_super_admin_command_promotes_an_existing_user(): void
    {
        $tenantAdmin = $this->adminFor();
        $tenantAdmin->forceFill(['email' => 'existing@example.test'])->save();
        $tenant = $tenantAdmin->tenant;

        $this->artisan('app:make-super-admin', [
            'email' => 'existing@example.test',
            '--password' => 'secret1234',
        ])->assertSuccessful();

        $promoted = $tenantAdmin->refresh();

        $this->assertSame(UserRole::SuperAdmin, $promoted->role);
        // A platform account is tenant-less and detached from the roster.
        $this->assertNull($promoted->tenant_id);
        $this->assertFalse($promoted->firms()->where('tenants.id', $tenant->id)->exists());
        // The tenant and its data survive untouched.
        $this->assertSame('active', $tenant->refresh()->status);
    }

    public function test_a_tenant_admin_cannot_assign_the_super_admin_role(): void
    {
        $tenantAdmin = $this->adminFor();

        $this->actingAs($tenantAdmin)->post(route('users.store'), [
            'name' => 'Backdoor',
            'email' => 'backdoor@example.test',
            'role' => UserRole::SuperAdmin->value,
            'password' => 'secret1234',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'backdoor@example.test']);
    }

    public function test_a_tenant_admin_cannot_promote_someone_to_super_admin(): void
    {
        $tenantAdmin = $this->adminFor();
        $staff = $this->userWithRole(UserRole::InvoiceCreator, $tenantAdmin->tenant);

        $this->actingAs($tenantAdmin)->put(route('users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => UserRole::SuperAdmin->value,
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::InvoiceCreator, $staff->refresh()->role);
    }

    public function test_a_tenant_admin_can_assign_the_manager_role(): void
    {
        $tenantAdmin = $this->adminFor();

        $this->actingAs($tenantAdmin)->post(route('users.store'), [
            'name' => 'New Manager',
            'email' => 'manager@example.test',
            'role' => UserRole::Manager->value,
            'password' => 'secret1234',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = User::query()->withoutGlobalScopes()
            ->where('email', 'manager@example.test')->firstOrFail();

        $this->assertSame(UserRole::Manager, $created->role);
        $this->assertTrue($created->canDo(Permission::DeleteInvoices));
    }

    public function test_the_staff_screen_does_not_offer_the_super_admin_role(): void
    {
        $tenantAdmin = $this->adminFor();

        $this->actingAs($tenantAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('roles', fn ($roles) => collect($roles)->pluck('value')->all() === [
                    UserRole::Admin->value,
                    UserRole::Manager->value,
                    UserRole::InvoiceCreator->value,
                    UserRole::Viewer->value,
                ])
            );
    }

    public function test_the_super_admin_command_validates_the_email(): void
    {
        $this->artisan('app:make-super-admin', [
            'email' => 'not-an-email',
            '--password' => 'secret1234',
        ])->assertFailed();
    }

    public function test_super_admins_hold_the_platform_permissions(): void
    {
        $permissions = UserRole::SuperAdmin->permissions();

        $this->assertContains(Permission::ManageTenants->value, $permissions);
        $this->assertContains(Permission::ManageSubscriptions->value, $permissions);
        $this->assertContains(Permission::ImpersonateTenants->value, $permissions);
        // And the tenant-side rights, so they never hit a wall.
        $this->assertContains(Permission::ManageSettings->value, $permissions);
        $this->assertContains(Permission::CreateInvoices->value, $permissions);
    }

    public function test_the_super_admin_role_is_not_assignable_within_a_tenant(): void
    {
        $this->assertNotContains(UserRole::SuperAdmin, UserRole::assignable());
        $this->assertFalse(UserRole::Admin->belongsToTenant() === false);
        $this->assertFalse(UserRole::SuperAdmin->belongsToTenant());
    }
}
