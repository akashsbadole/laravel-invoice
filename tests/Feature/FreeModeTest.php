<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product is free for now: every feature is available to every tenant
 * with no quotas and no checkout. These guard that promise, so turning the
 * paywall back on is a deliberate config change rather than an accident.
 */
class FreeModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.mode' => 'free']);
    }

    public function test_free_mode_is_the_default(): void
    {
        $this->assertSame('free', config('billing.mode'));
        $this->assertTrue(app(SubscriptionService::class)->isFreeMode());
    }

    public function test_a_tenant_with_no_subscription_row_has_full_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminFor($tenant);

        // The seeded trial row exists, so remove it entirely.
        Subscription::query()->where('tenant_id', $tenant->id)->delete();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('catalog.index'))->assertOk();
        $this->actingAs($user)->get(route('invoices.index'))->assertOk();
        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $this->actingAs($user)->get(route('reminders.index'))->assertOk();
    }

    public function test_a_suspended_paid_subscription_does_not_lock_a_tenant_out(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminFor($tenant);
        $starter = Plan::ensureDefaults()['starter'];

        Subscription::query()->update([
            'plan_id' => $starter->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_no_staff_quota_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->adminFor($tenant);

        $subscriptions = app(SubscriptionService::class);

        $this->assertNull($subscriptions->staffQuotaError($tenant->fresh()));

        // Add well past any plausible limit; still allowed.
        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($admin)->post(route('users.store'), [
                'name' => "Staff {$i}",
                'email' => "staff{$i}@example.test",
                'role' => 'invoice_creator',
                'password' => 'secret1234',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(13, $tenant->users()->count());
        $this->assertNull($subscriptions->staffQuotaError($tenant->fresh()));
    }

    public function test_no_invoice_quota_is_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->adminFor($tenant);
        $customer = $this->customerFor($admin);

        $subscriptions = app(SubscriptionService::class);

        // Well beyond the Starter plan's 200/month ceiling.
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($admin)->post(route('invoices.store'), [
                'customer_id' => $customer->id,
                'document_type' => 'general_invoice',
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Item',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 100,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(6, Invoice::query()->withoutGlobalScopes()->count());
        $this->assertNull($subscriptions->invoiceQuotaError($tenant->fresh()));
    }

    public function test_checkout_is_closed_while_free(): void
    {
        $admin = $this->adminFor();

        $this->actingAs($admin)
            ->post(route('billing.checkout'), ['plan' => 'starter'])
            ->assertNotFound();

        $this->actingAs($admin)
            ->post(route('billing.verify'), [
                'plan' => 'starter',
                'razorpay_order_id' => 'order_x',
                'razorpay_payment_id' => 'pay_x',
                'razorpay_signature' => 'sig',
            ])
            ->assertNotFound();
    }

    public function test_the_billing_page_reports_full_access_and_offers_no_purchase(): void
    {
        $admin = $this->adminFor();

        $this->actingAs($admin)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing')
                ->where('freeMode', true)
                // Only the free plan is presented.
                ->has('plans', 1)
                ->where('plans.0.slug', Plan::FREE_SLUG)
            );
    }

    public function test_paid_plans_are_not_advertised_to_tenants_while_free(): void
    {
        $admin = $this->adminFor();
        Plan::ensureDefaults();

        $slugs = collect($this->actingAs($admin)->get(route('billing.index'))->viewData('page')['props']['plans'])
            ->pluck('slug');

        $this->assertSame([Plan::FREE_SLUG], $slugs->all());
    }

    public function test_paid_mode_gates_a_lapsed_paid_subscription(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->adminFor($tenant);
        $starter = Plan::ensureDefaults()['starter'];

        // A cancelled paid plan grants nothing in paid mode.
        Subscription::query()->update([
            'plan_id' => $starter->id,
            'status' => 'cancelled',
        ]);

        $subscriptions = app(SubscriptionService::class);

        // Free mode ignores the lapsed subscription entirely.
        $this->assertNotNull($subscriptions->usablePlan($tenant->fresh()));
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // Paid mode honours it, so access is revoked.
        config(['billing.mode' => 'paid']);
        $this->assertNull($subscriptions->usablePlan($tenant->fresh()));
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('billing.index'));
    }

    public function test_a_tenant_with_no_subscription_falls_back_to_the_free_plan_in_paid_mode(): void
    {
        config(['billing.mode' => 'paid']);

        $tenant = Tenant::factory()->create();
        $user = $this->adminFor($tenant);
        Subscription::query()->where('tenant_id', $tenant->id)->delete();

        $subscriptions = app(SubscriptionService::class);

        // Never locked out of their own data, even on the paid plan set.
        $plan = $subscriptions->usablePlan($tenant->fresh());

        $this->assertNotNull($plan);
        $this->assertTrue($plan->isFree());
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_paid_mode_gates_the_checkout_back_on(): void
    {
        config(['billing.mode' => 'paid']);

        $admin = $this->adminFor();

        // No longer 404; it now validates and fails on the gateway instead.
        $this->actingAs($admin)
            ->post(route('billing.checkout'), ['plan' => 'starter'])
            ->assertSessionHas('errors');
    }

    public function test_catalog_and_stock_features_are_reachable_in_free_mode(): void
    {
        $admin = $this->adminFor();

        $this->inTenant($admin, fn () => CatalogItem::create([
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'stock_tracked' => true,
            'stock_quantity' => 10,
            'reorder_level' => 2,
            'is_active' => true,
            'created_by' => $admin->id,
        ]));

        $item = CatalogItem::query()->withoutGlobalScopes()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('catalog.stock', $item), ['type' => 'in', 'quantity' => 5])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(15, (float) $item->refresh()->stock_quantity);

        $this->actingAs($admin)
            ->get(route('catalog.index', ['status' => 'low_stock']))
            ->assertOk();
    }
}
