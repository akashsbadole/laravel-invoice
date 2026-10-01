<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\InventoryMovement;
use App\Models\Tenant;
use App\Support\CatalogField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tilesTenant(): Tenant
    {
        return Tenant::factory()->forIndustry('tiles_marble')->create();
    }

    protected function item(array $overrides = []): CatalogItem
    {
        return CatalogItem::create(array_merge([
            'name' => 'Vitrified Tile',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'is_active' => true,
        ], $overrides));
    }

    public function test_the_commercial_fields_are_persisted(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve 2 inch',
            'rate_type' => 'per_piece',
            'default_rate' => 750,
            'cost_price' => 520.5,
            'minimum_order_quantity' => 6,
            'pack_size' => 'Box of 12',
            'tax_inclusive' => true,
            'barcode' => '8901234567890',
            'manufacturer' => 'Jindal',
            'country_of_origin' => 'India',
            'warranty_months' => 24,
            'color' => 'Chrome',
            'material' => 'Brass',
            'thickness' => '2mm',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = CatalogItem::firstOrFail();

        $this->assertEquals(520.5, $item->cost_price);
        $this->assertSame(6, $item->minimum_order_quantity);
        $this->assertSame('Box of 12', $item->pack_size);
        $this->assertTrue($item->tax_inclusive);
        $this->assertSame('8901234567890', $item->barcode);
        $this->assertSame(24, $item->warranty_months);
        $this->assertSame('Brass', $item->material);
    }

    public function test_custom_attributes_are_saved_as_a_key_value_array(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'attributes' => ['thread' => '2x40', 'finish' => 'matte'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            ['thread' => '2x40', 'finish' => 'matte'],
            CatalogItem::firstOrFail()->attributes,
        );
    }

    public function test_blank_attribute_rows_are_discarded(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'attributes' => ['thread' => '2x40', '  ' => 'orphan', 'finish' => ''],
        ])->assertRedirect();

        $this->assertSame(['thread' => '2x40'], CatalogItem::firstOrFail()->attributes);
    }

    public function test_clearing_a_text_field_actually_clears_the_column(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => $this->item(['brand' => 'Jindal']));

        $this->actingAs($user)->put(route('catalog.update', $item), [
            'name' => $item->name,
            'rate_type' => 'per_piece',
            'brand' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($item->refresh()->brand);
    }

    public function test_a_product_image_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'image' => UploadedFile::fake()->image('valve.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = CatalogItem::firstOrFail();

        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);

        $this->actingAs($user)->put(route('catalog.update', $item), [
            'name' => $item->name,
            'rate_type' => 'per_piece',
            'remove_image' => true,
        ])->assertRedirect();

        $this->assertNull($item->refresh()->image_path);
        Storage::disk('public')->assertMissing($item->image_path);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'image' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
        ])->assertSessionHasErrors('image');
    }

    public function test_opening_stock_is_recorded_in_the_ledger(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)->post(route('catalog.store'), [
            'name' => 'Ball Valve',
            'rate_type' => 'per_piece',
            'stock_tracked' => true,
            'stock_quantity' => 25,
            'reorder_level' => 5,
            'stock_unit' => 'pcs',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = CatalogItem::firstOrFail();

        $this->assertEquals(25, $item->stock_quantity);
        $this->assertSame(1, $item->movements()->count());
        $this->assertEquals(25, $item->movements()->firstOrFail()->balance_after);
    }

    public function test_stock_in_and_out_move_the_balance(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => $this->item([
            'stock_tracked' => true,
            'stock_quantity' => 10,
        ]));

        $this->actingAs($user)->post(route('catalog.stock', $item), [
            'type' => 'in',
            'quantity' => 15,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(25, $item->refresh()->stock_quantity);

        $this->actingAs($user)->post(route('catalog.stock', $item), [
            'type' => 'out',
            'quantity' => 5,
            'note' => 'Sold at counter',
        ])->assertRedirect();

        $item->refresh();
        $this->assertEquals(20, $item->stock_quantity);
        $this->assertSame(2, $item->movements()->count());
        $this->assertSame('Sold at counter', $item->movements()->latest('id')->firstOrFail()->note);
    }

    public function test_an_adjustment_sets_the_balance_exactly(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => $this->item([
            'stock_tracked' => true,
            'stock_quantity' => 30,
        ]));

        $this->actingAs($user)->post(route('catalog.stock', $item), [
            'type' => 'adjustment',
            'quantity' => 7,
            'reason' => 'Stock count',
        ])->assertRedirect();

        $item->refresh();

        $this->assertEquals(7, $item->stock_quantity);
        $this->assertSame(InventoryMovement::TYPE_ADJUSTMENT, $item->movements()->latest('id')->firstOrFail()->type);
    }

    public function test_stock_cannot_be_adjusted_for_an_untracked_product(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => $this->item(['stock_tracked' => false]));

        $this->actingAs($user)
            ->post(route('catalog.stock', $item), ['type' => 'in', 'quantity' => 5])
            ->assertStatus(422);
    }

    public function test_a_zero_quantity_movement_is_rejected(): void
    {
        $user = $this->adminFor();
        $item = $this->inTenant($user, fn () => $this->item([
            'stock_tracked' => true,
            'stock_quantity' => 5,
        ]));

        $this->actingAs($user)
            ->post(route('catalog.stock', $item), ['type' => 'in', 'quantity' => 0])
            ->assertSessionHasErrors('quantity');
    }

    public function test_low_stock_is_flagged_only_when_tracked_and_at_or_below_the_reorder_level(): void
    {
        $low = $this->item(['stock_tracked' => true, 'stock_quantity' => 2, 'reorder_level' => 5]);
        $healthy = $this->item(['stock_tracked' => true, 'stock_quantity' => 20, 'reorder_level' => 5]);
        $untracked = $this->item(['stock_tracked' => false, 'stock_quantity' => 0, 'reorder_level' => 5]);
        $noThreshold = $this->item(['stock_tracked' => true, 'stock_quantity' => 0, 'reorder_level' => 0]);

        $this->assertTrue($low->isLowOnStock());
        $this->assertFalse($healthy->isLowOnStock());
        $this->assertFalse($untracked->isLowOnStock());
        $this->assertFalse($noThreshold->isLowOnStock());
    }

    public function test_the_catalog_can_be_filtered_to_low_stock_products(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, fn () => $this->item([
            'name' => 'Low one',
            'stock_tracked' => true,
            'stock_quantity' => 1,
            'reorder_level' => 5,
        ]));
        $this->inTenant($user, fn () => $this->item([
            'name' => 'Plenty',
            'stock_tracked' => true,
            'stock_quantity' => 50,
            'reorder_level' => 5,
        ]));

        $this->actingAs($user)
            ->get(route('catalog.index', ['status' => 'low_stock']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('catalog/index')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Low one')
                ->where('lowStockCount', 1)
            );
    }

    public function test_the_search_covers_barcode(): void
    {
        $user = $this->adminFor();
        $this->inTenant($user, fn () => $this->item([
            'name' => 'Ball Valve',
            'barcode' => '8901234567890',
        ]));

        $this->actingAs($user)
            ->get(route('catalog.index', ['search' => '8901234567890']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }

    public function test_the_form_receives_the_registry_fields_for_the_industry(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Area pricing fields are relevant to tiles.
                ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('default_length'))
                ->where('industry.show_all_fields', false)
            );
    }

    public function test_the_all_fields_toggle_reveals_every_registry_field(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)->post(route('business.update'), $this->businessPayload([
            'show_all_catalog_fields' => true,
        ]))->assertRedirect();

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertInertia(fn ($page) => $page
                ->where('industry.show_all_fields', true)
                ->where('fields', fn ($fields) => collect($fields)->pluck('name')->contains('metal_type'))
            );
    }

    public function test_the_registry_excludes_trade_specific_fields_for_other_industries(): void
    {
        $fields = CatalogField::names('tiles_marble');

        $this->assertContains('default_length', $fields);
        $this->assertNotContains('metal_type', $fields);

        $all = CatalogField::names('tiles_marble', true);

        $this->assertContains('metal_type', $all);
        $this->assertContains('purity', $all);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    protected function businessPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Tile House',
            'industry' => 'tiles_marble',
            'unit_label' => 'sq ft',
            'invoice_prefix' => 'INV-',
            'invoice_number_start' => 1,
            'default_tax_rate' => 18,
            'default_currency' => 'INR',
            'receipt_width' => 80,
            'state_code' => '27',
            'sms_driver' => 'log',
            'sms_country_code' => '+91',
            'quotation_prefix' => 'QT-',
            'next_quotation_sequence' => 1,
            'challan_prefix' => 'CH-',
            'next_challan_sequence' => 1,
            'sms_payment_reminders' => false,
            'sms_birthday_wishes' => false,
            'sms_anniversary_wishes' => false,
            'quotation_customer_decisions' => false,
            'quotation_show_updates' => true,
        ], $overrides);
    }
}
