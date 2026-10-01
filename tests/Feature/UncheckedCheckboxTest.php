<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\ChargeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unchecking an HTML checkbox omits the key entirely. These assert that the
 * affected forms still turn the flag OFF rather than keeping the old value.
 */
class UncheckedCheckboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_catalog_item_can_be_deactivated_from_the_edit_form(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Gold Ring',
            'rate_type' => 'fixed',
            'default_rate' => 5000,
            'is_active' => true,
            'created_by' => $user->id,
        ]));

        // The browser omits is_active when the box is cleared.
        $this->actingAs($user)
            ->put(route('catalog.update', $item), [
                'name' => 'Gold Ring',
                'rate_type' => 'fixed',
                'default_rate' => 5000,
            ])
            ->assertRedirect();

        $this->assertFalse($item->refresh()->is_active);
    }

    public function test_a_charge_type_can_be_deactivated_from_the_edit_form(): void
    {
        $user = $this->adminFor();
        $charge = $this->inTenant($user, fn () => ChargeType::create([
            'name' => 'Making Charge',
            'code' => 'MAKING',
            'is_taxable' => true,
            'is_active' => true,
        ]));

        $this->actingAs($user)
            ->put(route('charge-types.update', $charge), [
                'name' => 'Making Charge',
                'calculation_type' => 'fixed',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $charge->refresh();
        $this->assertFalse($charge->is_active);
        $this->assertFalse($charge->is_taxable);
    }

    public function test_a_staff_user_can_be_deactivated_from_the_edit_form(): void
    {
        $admin = $this->adminFor();
        $staff = $this->inTenant($admin, fn () => User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Staff Member',
            'email' => 'staff@example.test',
            'password' => bcrypt('secret1234'),
            'role' => 'invoice_creator',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->put(route('users.update', $staff), [
                'name' => 'Staff Member',
                'email' => 'staff@example.test',
                'role' => 'invoice_creator',
            ])
            ->assertRedirect();

        $this->assertFalse($staff->refresh()->is_active);
    }
}
