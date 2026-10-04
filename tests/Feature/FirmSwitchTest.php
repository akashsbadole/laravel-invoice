<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FirmSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_firm_stores_tenant_in_session_without_overwriting_user_table(): void
    {
        $tenant1 = Tenant::factory()->create(['name' => 'Firm One']);
        $tenant2 = Tenant::factory()->create(['name' => 'Firm Two']);

        $user = $this->adminFor($tenant1);
        $originalTenantId = $user->tenant_id;

        // Attach user to second tenant as manager
        $user->firms()->attach($tenant2->id, ['role' => UserRole::Manager->value]);

        $this->actingAs($user)
            ->post(route('firms.switch'), ['tenant_id' => $tenant2->id])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('current_tenant_id', $tenant2->id);

        // The user model in DB must still keep original tenant_id
        $this->assertSame($originalTenantId, $user->fresh()->getRawOriginal('tenant_id'));
    }
}
