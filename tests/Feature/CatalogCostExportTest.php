<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCostExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_csv_omits_cost_price_for_manager_role(): void
    {
        $manager = $this->userWithRole(UserRole::Manager);

        $this->inTenant($manager, fn () => CatalogItem::create([
            'name' => 'Sample Ring',
            'item_code' => 'RING-01',
            'rate_type' => 'per_piece',
            'default_rate' => 1000,
            'cost_price' => 750,
            'status' => 'active',
            'created_by' => $manager->id,
        ]));

        $response = $this->actingAs($manager)
            ->get(route('catalog.export'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('cost_price', $content);
        $this->assertStringNotContainsString('750', $content);
    }

    public function test_export_csv_includes_cost_price_for_admin_role(): void
    {
        $admin = $this->adminFor();

        $this->inTenant($admin, fn () => CatalogItem::create([
            'name' => 'Sample Ring',
            'item_code' => 'RING-01',
            'rate_type' => 'per_piece',
            'default_rate' => 1000,
            'cost_price' => 750,
            'status' => 'active',
            'created_by' => $admin->id,
        ]));

        $response = $this->actingAs($admin)
            ->get(route('catalog.export'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringContainsString('cost_price', $content);
        $this->assertStringContainsString('750', $content);
    }
}
