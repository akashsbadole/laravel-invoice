<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPolicyPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_manage_customers_can_delete_a_customer(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $user = $this->userWithRole(UserRole::Admin, $tenant);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_a_user_without_delete_customers_cannot_delete_a_customer(): void
    {
        $tenant = Tenant::factory()->create();
        $this->subscribe($tenant);

        $viewer = $this->userWithRole(UserRole::Viewer, $tenant);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($viewer)
            ->delete(route('customers.destroy', $customer))
            ->assertForbidden();
    }

    public function test_a_user_cannot_delete_a_customer_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $this->subscribe($tenantA);
        $this->subscribe($tenantB);

        $userA = $this->userWithRole(UserRole::Admin, $tenantA);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($userA)
            ->delete(route('customers.destroy', $customerB))
            ->assertNotFound();
    }
}
