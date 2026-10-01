<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_reports_catalog_counts(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, function () use ($user) {
            CatalogItem::create([
                'name' => 'Vitrified Floor Tile',
                'brand' => 'Nirogran',
                'rate_type' => 'per_sqft',
                'default_rate' => 55,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            CatalogItem::create([
                'name' => 'Retired Mosaic',
                'rate_type' => 'per_sqft',
                'is_active' => false,
                'created_by' => $user->id,
            ]);
        });

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('catalog.total', 2)
                ->where('catalog.active', 1)
                ->has('catalog.recent', 2)
                ->where('catalog.recent.0.name', 'Retired Mosaic')
            );
    }

    public function test_the_catalog_is_empty_before_any_products_are_added(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catalog.total', 0)
                ->where('catalog.active', 0)
                ->has('catalog.recent', 0)
            );
    }
}
