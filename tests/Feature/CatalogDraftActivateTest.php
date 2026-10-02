<?php

namespace Tests\Feature;

use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogDraftActivateTest extends TestCase
{
    use RefreshDatabase;

    protected function tilesTenant(): Tenant
    {
        return Tenant::factory()->forIndustry('tiles_marble')->create();
    }

    protected function draftItem($user, array $overrides = []): CatalogItem
    {
        return $this->inTenant($user, fn () => CatalogItem::create(array_merge([
            'name' => 'Draft Tile',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'stock_tracked' => true,
            'stock_quantity' => 0,
            'reorder_level' => 2,
            'status' => CatalogStatus::Draft,
            'created_by' => $user->id,
        ], $overrides)));
    }

    public function test_drafts_are_excluded_from_the_quotation_builder(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->draftItem($user, ['name' => 'Draft Tile']);
        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Live Tile',
            'rate_type' => 'per_sqft',
            'default_rate' => 60,
            'status' => CatalogStatus::Active,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('quotations.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('products', fn ($products) => $products->count() === 1)
                ->where('products.0.name', 'Live Tile'));
    }

    public function test_drafts_are_excluded_from_the_invoice_picker(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->draftItem($user, ['name' => 'Draft Item']);
        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Catalog Item',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'status' => CatalogStatus::Active,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catalogItems', fn ($items) => $items->count() === 1)
                ->where('catalogItems.0.name', 'Catalog Item'));
    }

    public function test_a_draft_item_can_be_activated_via_the_activate_route(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $item = $this->draftItem($user);

        $this->actingAs($user)
            ->post(route('catalog.activate', $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->status === CatalogStatus::Active);
    }

    public function test_a_non_draft_item_cannot_be_activated_via_the_activate_route(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $item = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Live Item',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'status' => CatalogStatus::Active,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->post(route('catalog.activate', $item))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertTrue($item->fresh()->status === CatalogStatus::Active);
    }

    public function test_multiple_drafts_can_be_activated_via_bulk_route(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $first = $this->draftItem($user, ['name' => 'First Draft']);
        $second = $this->draftItem($user, ['name' => 'Second Draft']);

        $this->actingAs($user)
            ->post(route('catalog.activate-selected'), [
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($first->fresh()->status === CatalogStatus::Active);
        $this->assertTrue($second->fresh()->status === CatalogStatus::Active);
    }

    public function test_the_draft_status_filter_lists_only_drafts(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->draftItem($user, ['name' => 'Draft Tile']);
        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Active Tile',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'status' => CatalogStatus::Active,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('catalog.index', ['status' => 'draft']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data', fn ($items) => $items->count() === 1)
                ->where('items.data.0.name', 'Draft Tile'));
    }

    public function test_csv_import_can_set_status_to_draft(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,item_code,rate_type,default_rate,status\nDraft Tile,BV-DRAFT,per_sqft,55,draft\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = CatalogItem::firstOrFail();
        $this->assertTrue($item->status === CatalogStatus::Draft);
    }

    public function test_the_dashboard_reports_a_draft_count(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->draftItem($user);
        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Active Tile',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'status' => CatalogStatus::Active,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('catalog.total', 2)
                ->where('catalog.active', 1)
                ->where('catalog.drafts', 1));
    }

    public function test_a_draft_is_auto_activated_when_stock_is_added(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $item = $this->draftItem($user);

        $this->actingAs($user)
            ->put(route('catalog.update', $item), [
                'name' => 'Draft Tile',
                'rate_type' => 'per_sqft',
                'default_rate' => 55,
                'stock_tracked' => true,
                'stock_quantity' => 100,
                'status' => CatalogStatus::Draft->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->status === CatalogStatus::Active);
    }

    protected function csv(string $contents, string $filename = 'catalog.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $filename, 'text/csv', null, true);
    }
}
