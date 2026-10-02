<?php

namespace Tests\Feature;

use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tilesTenant(): Tenant
    {
        return Tenant::factory()->forIndustry('tiles_marble')->create();
    }

    protected function product(User $user, array $overrides = []): CatalogItem
    {
        return $this->inTenant($user, fn () => CatalogItem::create(array_merge([
            'name' => 'Vitrified Floor Tile 600x600',
            'item_code' => 'TILE-600',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'status' => 'active',
            'created_by' => $user->id,
        ], $overrides)));
    }

    public function test_discontinued_products_are_not_offered_to_the_builder(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user, ['status' => 'discontinued']);

        $this->actingAs($user)
            ->get(route('quotations.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 0));
    }

    public function test_discontinued_products_are_not_offered_to_the_invoice_picker(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user, ['status' => 'discontinued']);

        $this->actingAs($user)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('catalogItems', 0));
    }

    public function test_the_discontinued_filter_lists_only_discontinued_items(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user, ['name' => 'Retired Tile', 'status' => 'discontinued']);
        $this->product($user, ['name' => 'Live Tile', 'item_code' => 'TILE-LIVE']);

        $this->actingAs($user)
            ->get(route('catalog.index', ['status' => 'discontinued']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data', fn ($items) => count($items) === 1)
                ->where('items.data.0.name', 'Retired Tile'));
    }

    public function test_the_inactive_filter_lists_only_inactive_items(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user, ['name' => 'Paused Tile', 'status' => 'inactive']);
        $this->product($user, ['name' => 'Retired Tile', 'item_code' => 'TILE-RET', 'status' => 'discontinued']);

        $this->actingAs($user)
            ->get(route('catalog.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('items.data', fn ($items) => count($items) === 1)
                ->where('items.data.0.name', 'Paused Tile'));
    }

    public function test_a_discontinued_product_can_be_activated_again(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $item = $this->product($user, ['status' => 'discontinued']);

        $this->actingAs($user)
            ->post(route('catalog.activate', $item))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($item->fresh()->status === CatalogStatus::Active);
    }

    public function test_an_active_product_cannot_be_activated_again(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $item = $this->product($user);

        $this->actingAs($user)
            ->post(route('catalog.activate', $item))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_csv_import_can_set_status_to_discontinued(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => new UploadedFile(
                    $this->tempFile("name,item_code,status\nRetired Tile,TILE-RET,discontinued\n"),
                    'catalog.csv',
                    'text/csv',
                    null,
                    true,
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('discontinued', CatalogItem::firstOrFail()->status->value);
    }

    public function test_an_unknown_status_falls_back_to_active(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => new UploadedFile(
                    $this->tempFile("name,item_code,status\nRetired Tile,TILE-RET,mystery\n"),
                    'catalog.csv',
                    'text/csv',
                    null,
                    true,
                ),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('active', CatalogItem::firstOrFail()->status->value);
    }

    protected function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return $path;
    }
}
