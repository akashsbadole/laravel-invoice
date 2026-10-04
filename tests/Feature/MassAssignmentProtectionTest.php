<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MassAssignmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_is_active_cannot_be_mass_assigned_on_user(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $admin = $this->userWithRole(UserRole::Admin, $tenant);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Viewer,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('users.update', $target), [
                'name' => 'Hacked',
                'email' => $target->email,
                'role' => UserRole::Admin->value,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $target->refresh();

        $this->assertSame(UserRole::Viewer, $target->role);
        $this->assertTrue($target->is_active);
    }

    public function test_discount_approval_fields_cannot_be_mass_assigned_on_invoice(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $admin = $this->userWithRole(UserRole::Admin, $tenant);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'document_type' => 'general_invoice',
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'invoice_date' => now(),
            'status' => 'unpaid',
            'pricing_mode' => 'manual',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'balance_amount' => 1000,
            'created_by' => $admin->id,
        ]);

        $invoice->fill([
            'discount_approved_by' => $admin->id,
            'discount_approved_at' => now(),
            'discount_approved_discount' => 500,
        ]);
        $invoice->save();

        $invoice->refresh();

        $this->assertNull($invoice->discount_approved_by);
        $this->assertNull($invoice->discount_approved_at);
        $this->assertNull($invoice->discount_approved_discount);
    }
}
