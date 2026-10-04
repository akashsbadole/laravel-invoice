<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\LineType;
use App\Models\CatalogItem;
use App\Models\CatalogVariant;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * One product in many sellable forms — a ring in four sizes, a pendant in
 * three purities — without turning every form into a separate product row.
 *
 * What the owner is buying with this feature: a code, a price and a stock
 * count per form, a product total that still means something, and an invoice
 * line that says *which* form was sold.
 */
class CatalogVariantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function variantRow(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Size 12',
            'item_code' => 'RNG-12',
            'rate' => 12000,
            'stock_quantity' => 4,
            'is_active' => '1',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Solitaire Ring',
            'item_code' => 'RNG',
            'rate_type' => 'per_piece',
            'default_rate' => 11000,
            'stock_quantity' => 0,
            'stock_tracked' => '1',
        ], $overrides);
    }

    protected function product($user, array $overrides = []): CatalogItem
    {
        return $this->inTenant($user, fn () => CatalogItem::create(array_merge([
            'name' => 'Solitaire Ring',
            'item_code' => 'RNG',
            'rate_type' => 'per_piece',
            'default_rate' => 11000,
            'status' => 'active',
            'created_by' => $user->id,
        ], $overrides)));
    }

    public function test_a_product_can_be_split_into_variants_with_their_own_code_price_and_stock(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [
                    $this->variantRow(),
                    $this->variantRow([
                        'label' => 'Size 14',
                        'item_code' => 'RNG-14',
                        'rate' => 13500,
                        'stock_quantity' => 6,
                    ]),
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = CatalogItem::sole();

        $this->assertSame(
            ['RNG-12', 'RNG-14'],
            $item->variants()->orderBy('sort_order')->pluck('item_code')->all(),
        );

        // The product row mirrors its variants rather than its own form field.
        $this->assertEquals(10.0, (float) $item->stock_quantity);
        $this->assertTrue($item->hasVariants());

        // Every figure arrives through the ledger, so it can be explained.
        $opening = InventoryMovement::query()
            ->where('catalog_item_id', $item->id)
            ->where('reason', 'Opening stock')
            ->get();

        $this->assertCount(2, $opening);
        $this->assertNotNull($opening->first()->catalog_variant_id);
        $this->assertEquals(4.0, (float) $opening->first()->quantity);
    }

    public function test_a_variant_code_may_not_collide_with_a_product_code_in_the_same_tenant(): void
    {
        $user = $this->adminFor();
        $this->product($user, ['item_code' => 'RNG-12']);

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'item_code' => 'SET',
                'variants' => [$this->variantRow(['item_code' => 'RNG-12'])],
            ]))
            ->assertSessionHasErrors('variants.0.item_code');

        $this->assertSame(
            'This item code is already used in your catalog.',
            session('errors')->first('variants.0.item_code'),
        );
    }

    public function test_two_variants_of_the_same_product_may_not_share_a_code(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [
                    $this->variantRow(),
                    $this->variantRow(['label' => 'Size 14']),
                ],
            ]))
            ->assertSessionHasErrors('variants.1.item_code');

        $this->assertDatabaseCount('catalog_variants', 0);
    }

    public function test_the_edit_dialog_gets_every_variant_row_back(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/index')
                ->where('items.data.0.variants.0.label', 'Size 12')
                ->where('items.data.0.variants.0.item_code', 'RNG-12')
                ->where('items.data.0.variants.0.rate', '12000.00')
                ->where('items.data.0.variants.0.is_active', true));
    }

    public function test_editing_a_product_syncs_the_rows_that_changed_and_drops_the_rest(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [
                    $this->variantRow(),
                    $this->variantRow([
                        'label' => 'Size 14',
                        'item_code' => 'RNG-14',
                        'stock_quantity' => 6,
                    ]),
                ],
            ]))
            ->assertRedirect();

        $item = CatalogItem::sole();
        [$size12, $size14] = $item->variants()->orderBy('sort_order')->get();

        $this->actingAs($user)
            ->put(route('catalog.update', $item), [
                'name' => 'Solitaire Ring',
                'rate_type' => 'per_piece',
                'default_rate' => 11000,
                'status' => 'active',
                'variants' => [
                    [
                        'id' => $size12->id,
                        'label' => 'Size 12',
                        'item_code' => 'RNG-12',
                        // Corrected by hand, booked as an adjustment.
                        'rate' => 12500,
                        'stock_quantity' => 7,
                        'is_active' => '1',
                    ],
                    $this->variantRow([
                        'label' => 'Size 16',
                        'item_code' => 'RNG-16',
                        'stock_quantity' => 2,
                    ]),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_variants', [
            'id' => $size12->id,
            'rate' => '12500.00',
            'label' => 'Size 12',
        ]);

        // The size that was removed from the form is gone from the product…
        $this->assertDatabaseMissing('catalog_variants', ['id' => $size14->id]);

        // …but not from the ledger: its stock is written off, not erased.
        $this->assertDatabaseHas('inventory_movements', [
            'catalog_item_id' => $item->id,
            'catalog_variant_id' => $size14->id,
            'type' => InventoryMovement::TYPE_OUT,
            'reason' => 'Variant removed',
            'balance_after' => 0,
        ]);

        $this->assertSame(
            ['RNG-12', 'RNG-16'],
            $item->variants()->orderBy('sort_order')->pluck('item_code')->all(),
        );

        // 7 on the size kept + 2 on the size added; the 6 written off is out.
        $this->assertEquals(9.0, (float) $item->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_movements', [
            'catalog_item_id' => $item->id,
            'catalog_variant_id' => $size12->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'reason' => 'Stock edited',
            'balance_after' => 7,
        ]);
    }

    public function test_clearing_the_variants_returns_the_balance_to_the_product_row(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $item = CatalogItem::sole();

        $this->actingAs($user)
            ->put(route('catalog.update', $item), [
                'name' => 'Solitaire Ring',
                'rate_type' => 'per_piece',
                'default_rate' => 11000,
                'status' => 'active',
                'clear_variants' => '1',
                'stock_quantity' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $item->variants()->count());
        $this->assertEquals(5.0, (float) $item->fresh()->stock_quantity);
    }

    public function test_an_invoice_line_records_the_variant_it_sold(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow(['rate' => 12000])],
            ]))
            ->assertRedirect();

        $item = CatalogItem::sole();
        $variant = $item->variants()->sole();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'document_type' => 'general_invoice',
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Solitaire Ring (Size 12)',
                    'item_code' => 'RNG-12',
                    'catalog_item_id' => $item->id,
                    'catalog_variant_id' => $variant->id,
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 12000,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $line = Invoice::sole()->items()->sole();

        $this->assertSame($variant->id, $line->catalog_variant_id);
        $this->assertSame($item->id, $line->catalog_item_id);
    }

    public function test_an_invoice_line_cannot_borrow_a_variant_from_another_product(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $ring = $this->product($user, ['item_code' => 'RNG']);
        $pendant = $this->product($user, [
            'name' => 'Heart Pendant',
            'item_code' => 'PND',
        ]);

        $this->inTenant($user, function () use ($pendant): void {
            $pendant->variants()->create([
                'label' => '18 inch',
                'item_code' => 'PND-18',
                'stock_quantity' => 0,
            ]);
        });

        $foreign = $this->inTenant($user, fn () => $pendant->variants()->sole());

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'document_type' => 'general_invoice',
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [[
                    'item_name' => 'Solitaire Ring',
                    'catalog_item_id' => $ring->id,
                    'catalog_variant_id' => $foreign->id,
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 11000,
                    'tax_rate' => 0,
                    'discount' => 0,
                    'charges' => [],
                ]],
            ])
            ->assertSessionHasErrors('items.0.catalog_variant_id');

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_invoice_picker_hands_variants_to_the_line_editor(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/create')
                ->where('catalogItems.0.variants.0.item_code', 'RNG-12')
                ->where('catalogItems.0.variants.0.is_active', true));
    }

    public function test_a_split_product_refuses_a_product_level_stock_movement(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $item = CatalogItem::sole();

        $this->actingAs($user)
            ->post(route('catalog.stock', $item), [
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => 3,
                'reason' => 'Delivery',
            ])
            ->assertStatus(422);

        // The sum of the variants is left alone, not quietly overwritten.
        $this->assertEquals(4.0, (float) $item->fresh()->stock_quantity);
        $this->assertSame(
            ['Opening stock'],
            InventoryMovement::query()->pluck('reason')->all(),
        );
    }

    public function test_converting_a_quotation_carries_the_variant_and_the_line_type_across(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $item = CatalogItem::sole();
        $variant = $item->variants()->sole();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'document_type' => DocumentType::Quotation->value,
                'customer_id' => $customer->id,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'tax_rate' => 0,
                'discount' => 0,
                'invoice_charges' => [],
                'items' => [
                    [
                        'sort_order' => 1,
                        'line_type' => LineType::Sale->value,
                        'item_name' => 'Solitaire Ring (Size 12)',
                        'catalog_item_id' => $item->id,
                        'catalog_variant_id' => $variant->id,
                        'attributes' => ['job_number' => 'J-1'],
                        'quantity' => 1,
                        'rate_type' => 'fixed',
                        'rate' => 12000,
                        'tax_rate' => 0,
                        'discount' => 0,
                        'charges' => [],
                    ],
                    [
                        'sort_order' => 2,
                        'line_type' => LineType::ExchangeCredit->value,
                        'item_name' => 'Old gold exchange',
                        'quantity' => 1,
                        'rate_type' => 'fixed',
                        'rate' => 3000,
                        'tax_rate' => 0,
                        'discount' => 0,
                        'charges' => [],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation = Invoice::sole();

        $this->actingAs($user)
            ->post(route('invoices.convert', $quotation), [
                'document_type' => DocumentType::GeneralInvoice->value,
            ])
            ->assertRedirect();

        $invoice = $quotation->refresh()->convertedTo;
        $lines = $invoice->items()->orderBy('sort_order')->get();

        $this->assertSame(LineType::Sale, $lines[0]->line_type);
        $this->assertSame($variant->id, $lines[0]->catalog_variant_id);
        $this->assertSame($item->id, $lines[0]->catalog_item_id);
        $this->assertSame(['job_number' => 'J-1'], $lines[0]->attributes);

        $this->assertSame(LineType::ExchangeCredit, $lines[1]->line_type);
    }

    public function test_a_variant_name_lands_on_the_line_so_the_invoice_says_which_form_was_sold(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)
            ->post(route('catalog.store'), $this->productPayload([
                'variants' => [$this->variantRow()],
            ]))
            ->assertRedirect();

        $variant = CatalogVariant::sole();

        $this->assertSame('Solitaire Ring (Size 12)', $variant->describe(CatalogItem::sole()->name));
        $this->assertEquals(12000.0, $variant->effectiveRate());
    }
}
