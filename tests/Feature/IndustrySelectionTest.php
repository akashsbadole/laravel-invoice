<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\ChargeType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Industry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IndustrySelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_asks_which_industry_the_business_trades_in(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->has('industries', count(Industry::all()))
                ->where('industries.0.key', array_key_first(Industry::all()))
            );
    }

    public function test_registering_stores_the_chosen_industry_on_tenant_and_settings(): void
    {
        $this->post(route('register'), [
            'name' => 'Ravi Patel',
            'business_name' => 'Patel Tiles',
            'industry' => 'tiles_marble',
            'email' => 'ravi@pateltiles.test',
            'password' => 'password-123-Aa',
            'password_confirmation' => 'password-123-Aa',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Patel Tiles',
            'industry' => 'tiles_marble',
        ]);

        $this->assertDatabaseHas('business_settings', [
            'business_name' => 'Patel Tiles',
            'industry' => 'tiles_marble',
        ]);
    }

    public function test_registration_rejects_an_unknown_industry(): void
    {
        $this->post(route('register'), [
            'name' => 'Ravi Patel',
            'business_name' => 'Patel Tiles',
            'industry' => 'space_tourism',
            'email' => 'ravi@pateltiles.test',
            'password' => 'password-123-Aa',
            'password_confirmation' => 'password-123-Aa',
        ])->assertSessionHasErrors('industry');

        // The tenant backfill migration seeds one demo tenant, so assert the
        // rejected business never got one.
        $this->assertDatabaseMissing('tenants', ['industry' => 'space_tourism']);
        $this->assertDatabaseMissing('users', ['email' => 'ravi@pateltiles.test']);
    }

    public function test_an_admin_can_change_the_industry_in_business_settings(): void
    {
        $user = $this->adminFor();
        $settings = BusinessSetting::forTenant($user->tenant_id);

        $this->assertSame(Industry::default(), $settings->industry);

        $this->actingAs($user)->post(route('business.update'), [
            'business_name' => $settings->business_name,
            'industry' => 'hardware',
            'receipt_width' => '80',
            'sms_driver' => 'log',
            'sms_country_code' => '91',
            'invoice_prefix' => 'INV',
            'invoice_number_start' => 1,
            'quotation_prefix' => 'QT',
            'challan_prefix' => 'DC',
            'default_tax_rate' => 3,
            'default_currency' => 'INR',
        ])->assertRedirect();

        $this->assertDatabaseHas('business_settings', [
            'tenant_id' => $user->tenant_id,
            'industry' => 'hardware',
        ]);
    }

    public function test_switching_industry_deactivates_charge_types_the_new_trade_cannot_use(): void
    {
        $user = $this->adminFor();
        $settings = BusinessSetting::forTenant($user->tenant_id);

        // The tenant starts on jewelry, so hallmarking is an active charge.
        $this->assertTrue($this->chargeExists($user, 'Hallmarking Charge', true));

        $this->actingAs($user)->post(route('business.update'), [
            'business_name' => $settings->business_name,
            'industry' => 'tiles_marble',
            'receipt_width' => '80',
            'sms_driver' => 'log',
            'sms_country_code' => '91',
            'invoice_prefix' => 'INV',
            'invoice_number_start' => 1,
            'quotation_prefix' => 'QT',
            'challan_prefix' => 'DC',
            'default_tax_rate' => 3,
            'default_currency' => 'INR',
        ])->assertRedirect();

        $this->assertTrue($this->chargeExists($user, 'Hallmarking Charge', false));
        // ...and a trade-relevant charge becomes available.
        $this->assertTrue($this->chargeExists($user, 'Installation', true));
    }

    public function test_switching_industry_activates_charges_the_new_trade_needs(): void
    {
        $user = $this->adminFor();
        $settings = BusinessSetting::forTenant($user->tenant_id);

        $this->actingAs($user)->post(route('business.update'), [
            'business_name' => $settings->business_name,
            'industry' => 'hardware',
            'receipt_width' => '80',
            'sms_driver' => 'log',
            'sms_country_code' => '91',
            'invoice_prefix' => 'INV',
            'invoice_number_start' => 1,
            'quotation_prefix' => 'QT',
            'challan_prefix' => 'DC',
            'default_tax_rate' => 3,
            'default_currency' => 'INR',
        ])->assertRedirect();

        $this->assertTrue($this->chargeExists($user, 'Installation', true));
        $this->assertTrue($this->chargeExists($user, 'Hallmarking Charge', false));
    }

    /**
     * Charge types are tenant-scoped, so assert through the tenant rather
     * than against the raw table.
     */
    protected function chargeExists(User $user, string $name, bool $isActive): bool
    {
        return $this->inTenant($user, fn () => ChargeType::query()
            ->where('name', $name)
            ->where('is_active', $isActive)
            ->exists());
    }

    public function test_industry_capabilities_are_shared_with_the_frontend(): void
    {
        $tilesTenant = Tenant::factory()->forIndustry('tiles_marble')->create();
        $user = $this->adminFor($tilesTenant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.metal_rates', false)
            );
    }

    public function test_a_jewelry_tenant_keeps_the_metal_rate_capability(): void
    {
        $jewelryTenant = Tenant::factory()->forIndustry('jewelry')->create();
        $user = $this->adminFor($jewelryTenant);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.metal_rates', true)
            );
    }
}
