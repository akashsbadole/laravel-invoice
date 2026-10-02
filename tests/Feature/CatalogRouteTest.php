<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_lives_at_its_own_top_level_url(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get('/catalog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('catalog/index'));
    }

    public function test_the_old_settings_catalog_url_is_gone(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get('/settings/catalog')
            ->assertNotFound();
    }

    public function test_products_can_be_created_from_the_top_level_catalog(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve 2 inch',
            'brand' => 'Jindal',
            'model_number' => 'BV-200',
            'rate_type' => 'per_piece',
            'default_rate' => 750,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Ball Valve 2 inch',
            'brand' => 'Jindal',
            'status' => 'active',
        ]);
    }

    public function test_the_catalog_create_page_is_served_from_its_own_url(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('catalog.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('catalog/create'));
    }

    public function test_products_can_be_created_from_the_catalog_create_page(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), [
                'name' => 'Ball Valve 2 inch',
                'brand' => 'Jindal',
                'model_number' => 'BV-200',
                'rate_type' => 'per_piece',
                'default_rate' => 750,
            ])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Ball Valve 2 inch',
            'brand' => 'Jindal',
            'status' => 'active',
        ]);
    }

    public function test_products_can_be_updated_and_deleted_from_the_catalog(): void
    {
        $user = $this->adminFor();

        $item = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Old product',
            'rate_type' => 'per_piece',
            'status' => 'active',
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)->put(route('catalog.update', $item), [
            'name' => 'Renamed product',
            'rate_type' => 'per_piece',
            'default_rate' => 100,
        ])->assertRedirect();

        $this->assertSame('Renamed product', $item->refresh()->name);

        $this->actingAs($user)
            ->delete(route('catalog.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('catalog_items', ['id' => $item->id]);
    }

    public function test_the_catalog_template_and_export_are_served_from_the_top_level_path(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('catalog.template'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($user)
            ->get(route('catalog.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
