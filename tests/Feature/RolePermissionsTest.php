<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roles are defined in config/permissions.php. These guard the shape of that
 * matrix and the practical consequences, so a permission can be granted or
 * removed without silently changing what a role can actually do.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_is_present_in_the_permission_matrix(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotEmpty(
                $role->permissions(),
                "{$role->value} has no permissions configured.",
            );
        }
    }

    public function test_the_matrix_only_names_real_permissions(): void
    {
        $valid = array_map(fn (Permission $p) => $p->value, Permission::cases());

        foreach (UserRole::cases() as $role) {
            foreach ($role->permissions() as $permission) {
                $this->assertContains(
                    $permission,
                    $valid,
                    "{$role->value} references unknown permission [{$permission}].",
                );
            }
        }
    }

    public function test_a_viewer_can_only_read(): void
    {
        $viewer = UserRole::Viewer;

        $this->assertTrue($viewer->can(Permission::ViewInvoices));
        $this->assertTrue($viewer->can(Permission::ViewCustomers));
        $this->assertTrue($viewer->can(Permission::ViewCatalog));

        $this->assertFalse($viewer->can(Permission::CreateInvoices));
        $this->assertFalse($viewer->can(Permission::ManageCustomers));
        $this->assertFalse($viewer->can(Permission::ManageCatalog));
        $this->assertFalse($viewer->can(Permission::RecordPayments));
        $this->assertFalse($viewer->can(Permission::DeleteInvoices));
        $this->assertFalse($viewer->can(Permission::ManageSettings));
        $this->assertFalse($viewer->canWrite());
    }

    public function test_an_invoice_creator_can_work_but_not_delete_or_configure(): void
    {
        $creator = UserRole::InvoiceCreator;

        $this->assertTrue($creator->can(Permission::CreateInvoices));
        $this->assertTrue($creator->can(Permission::EditInvoices));
        $this->assertTrue($creator->can(Permission::RecordPayments));
        $this->assertTrue($creator->can(Permission::ManageCustomers));

        // New capability vs the old binary canWrite().
        $this->assertFalse($creator->can(Permission::DeleteInvoices));
        $this->assertFalse($creator->can(Permission::ManageCatalog));
        $this->assertFalse($creator->can(Permission::ManageSettings));
        $this->assertFalse($creator->can(Permission::ViewCosts));
    }

    public function test_a_manager_runs_the_business_without_touching_settings(): void
    {
        $manager = UserRole::Manager;

        $this->assertTrue($manager->can(Permission::DeleteInvoices));
        $this->assertTrue($manager->can(Permission::ManageCatalog));
        $this->assertTrue($manager->can(Permission::ManageInventory));
        $this->assertTrue($manager->can(Permission::SendMessages));
        $this->assertTrue($manager->can(Permission::ViewReports));

        $this->assertFalse($manager->can(Permission::ManageSettings));
        $this->assertFalse($manager->can(Permission::ManageUsers));
    }

    public function test_only_an_admin_and_a_super_admin_manage_settings_and_people(): void
    {
        foreach ([UserRole::Admin, UserRole::SuperAdmin] as $role) {
            $this->assertTrue($role->can(Permission::ManageSettings), $role->value);
            $this->assertTrue($role->can(Permission::ManageUsers), $role->value);
        }

        foreach ([UserRole::Manager, UserRole::InvoiceCreator, UserRole::Viewer] as $role) {
            $this->assertFalse($role->can(Permission::ManageSettings), $role->value);
            $this->assertFalse($role->can(Permission::ManageUsers), $role->value);
        }
    }

    public function test_only_a_super_admin_holds_platform_permissions(): void
    {
        $platform = [
            Permission::ManageTenants,
            Permission::ManagePlans,
            Permission::ManageSubscriptions,
            Permission::ImpersonateTenants,
            Permission::ViewPlatformReports,
        ];

        foreach (UserRole::cases() as $role) {
            foreach ($platform as $permission) {
                $expected = $role === UserRole::SuperAdmin;
                $this->assertSame(
                    $expected,
                    $role->can($permission),
                    "{$role->value} and {$permission->value}",
                );
            }
        }
    }

    public function test_cost_visibility_is_limited_to_roles_that_need_it(): void
    {
        $this->assertTrue(UserRole::Admin->can(Permission::ViewCosts));
        $this->assertTrue(UserRole::SuperAdmin->can(Permission::ViewCosts));
        $this->assertFalse(UserRole::Manager->can(Permission::ViewCosts));
        $this->assertFalse(UserRole::InvoiceCreator->can(Permission::ViewCosts));
        $this->assertFalse(UserRole::Viewer->can(Permission::ViewCosts));
    }

    public function test_a_viewer_cannot_reach_the_invoice_form(): void
    {
        $viewer = $this->userWithRole(UserRole::Viewer);

        $this->actingAs($viewer)
            ->get(route('invoices.create'))
            ->assertForbidden();
    }

    public function test_a_manager_cannot_reach_business_settings(): void
    {
        $manager = $this->userWithRole(UserRole::Manager);

        $this->actingAs($manager)
            ->get(route('business.edit'))
            ->assertForbidden();
    }

    public function test_an_invoice_creator_cannot_delete_an_invoice(): void
    {
        $creator = $this->userWithRole(UserRole::InvoiceCreator);
        $invoice = $this->invoiceFor($creator);

        $this->actingAs($creator)
            ->delete(route('invoices.destroy', $invoice))
            ->assertForbidden();
    }

    public function test_a_manager_can_delete_an_invoice(): void
    {
        $manager = $this->userWithRole(UserRole::Manager);
        $invoice = $this->invoiceFor($manager);

        $this->actingAs($manager)
            ->delete(route('invoices.destroy', $invoice))
            ->assertRedirect();

        // Invoices are soft-deleted so the ledger stays auditable.
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    protected function invoiceFor($user): Invoice
    {
        $customer = $this->customerFor($user);

        return $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'document_type' => 'general_invoice',
            'invoice_date' => now(),
            'status' => 'sent',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'balance_amount' => 1000,
            'created_by' => $user->id,
        ]));
    }

    /**
     * The product is free for now: the free plan must always grant access, so
     * nobody is redirected to checkout.
     */
    public function test_the_free_plan_always_grants_access(): void
    {
        $tenant = Tenant::factory()->create();
        $free = Plan::ensureDefaults()[Plan::FREE_SLUG];

        $subscriptions = app(SubscriptionService::class);

        // Even a cancelled subscription on the free plan stays usable.
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $free->id,
            'status' => 'cancelled',
        ]);

        $this->assertNotNull($subscriptions->usablePlan($tenant->fresh()));
        $this->assertFalse($subscriptions->hasPaidPlan($tenant->fresh()));
    }

    public function test_a_paid_plan_stops_granting_access_when_it_lapses(): void
    {
        // Only meaningful once the paywall is switched back on; see
        // FreeModeTest for the current behaviour.
        config(['billing.mode' => 'paid']);

        $tenant = Tenant::factory()->create();
        $starter = Plan::ensureDefaults()['starter'];

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starter->id,
            'status' => 'cancelled',
        ]);

        $this->assertNull(app(SubscriptionService::class)->usablePlan($tenant->fresh()));
    }

    public function test_the_free_plan_has_no_staff_or_invoice_ceiling(): void
    {
        $free = Plan::ensureDefaults()[Plan::FREE_SLUG];

        $this->assertTrue($free->isFree());
        $this->assertEquals(0, $free->price);
        $this->assertTrue($free->allowsUnlimitedInvoices());
        $this->assertTrue($free->allowsUnlimitedStaff());
    }

    public function test_a_tenant_on_the_free_plan_is_never_quota_blocked(): void
    {
        $tenant = Tenant::factory()->create();
        $free = Plan::ensureDefaults()[Plan::FREE_SLUG];

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $free->id,
            'status' => 'active',
        ]);

        $subscriptions = app(SubscriptionService::class);

        $this->assertNull($subscriptions->staffQuotaError($tenant->fresh()));
        $this->assertNull($subscriptions->invoiceQuotaError($tenant->fresh()));
    }

    public function test_registration_lands_on_the_free_plan_with_no_trial(): void
    {
        $this->post(route('register'), [
            'name' => 'Priya',
            'business_name' => 'Priya Textiles',
            'industry' => 'general',
            'email' => 'priya@example.test',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertRedirect();

        $tenant = Tenant::query()->where('name', 'Priya Textiles')->firstOrFail();

        $this->assertNull($tenant->trial_ends_at);
        $this->assertSame(
            Plan::FREE_SLUG,
            $tenant->subscription->plan->slug,
        );
        $this->assertSame('active', $tenant->subscription->status);
    }

    public function test_a_tenant_with_no_subscription_row_still_gets_the_free_plan(): void
    {
        $tenant = Tenant::factory()->create();

        $subscriptions = app(SubscriptionService::class);

        $plan = $subscriptions->usablePlan($tenant->fresh());

        $this->assertNotNull($plan);
        $this->assertTrue($plan->isFree());
        $this->assertNull($subscriptions->staffQuotaError($tenant->fresh()));
    }

    public function test_an_existing_paid_tenant_keeps_its_plan(): void
    {
        $tenant = $this->adminFor()->tenant;
        $starter = Plan::ensureDefaults()['starter'];

        // hasPaidPlan reflects the recorded subscription regardless of mode.
        $this->assertTrue(app(SubscriptionService::class)->hasPaidPlan($tenant->fresh()));

        // In free mode access comes from the free plan, not the paid one.
        $this->assertTrue(
            app(SubscriptionService::class)->usablePlan($tenant->fresh())->isFree(),
        );

        config(['billing.mode' => 'paid']);

        $this->assertSame(
            $starter->id,
            app(SubscriptionService::class)->usablePlan($tenant->fresh())->id,
        );
    }

    public function test_a_brand_new_tenant_can_reach_the_dashboard(): void
    {
        $this->post(route('register'), [
            'name' => 'Priya',
            'business_name' => 'Priya Textiles',
            'industry' => 'general',
            'email' => 'priya@example.test',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertRedirect();

        // No trial, no paid plan — the free plan must not bounce them to billing.
        $this->get(route('dashboard'))->assertOk();
    }
}
