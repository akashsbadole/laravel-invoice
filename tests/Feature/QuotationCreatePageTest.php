<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_quotation_builder_lives_at_its_own_url(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get('/quotations/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('quotations/create')
                ->has('products')
            );
    }

    public function test_the_quotation_builder_offers_active_products(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, function () use ($user) {
            CatalogItem::create([
                'name' => 'Diamond Ring',
                'item_code' => 'RNG-001',
                'rate_type' => 'per_gram',
                'default_rate' => 12000,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
        });

        $this->actingAs($user)
            ->get('/quotations/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products.0.name', 'Diamond Ring')
                ->where('products.0.rate_type', 'per_gram')
            );
    }

    public function test_active_products_are_offered_to_the_builder(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, function () use ($user) {
            CatalogItem::create([
                'name' => 'Gold Chain',
                'item_code' => 'CHN-001',
                'rate_type' => 'per_gram',
                'default_rate' => 5000,
                'status' => 'active',
                'created_by' => $user->id,
            ]);
            CatalogItem::create([
                'name' => 'Silver Earrings',
                'item_code' => 'EAR-001',
                'rate_type' => 'per_piece',
                'default_rate' => 800,
                'status' => 'inactive',
                'created_by' => $user->id,
            ]);
        });

        $this->actingAs($user)
            ->get('/quotations/create')
            ->assertInertia(fn ($page) => $page->has('products', 1));
    }
}
