<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function tilesTenant(): Tenant
    {
        return Tenant::factory()->forIndustry('tiles_marble')->create();
    }

    protected function product(User|\App\Models\User $user, array $overrides = []): CatalogItem
    {
        return $this->inTenant($user, fn () => CatalogItem::create(array_merge([
            'name' => 'Vitrified Floor Tile 600x600',
            'brand' => 'Nirogran',
            'item_code' => 'TILE-600',
            'hsn_code' => '6907',
            'size_label' => '600x600',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'default_length' => 60,
            'default_width' => 60,
            'default_wastage_percent' => 5,
            'is_active' => true,
            'created_by' => $user->id,
        ], $overrides)));
    }

    public function test_picking_catalog_products_opens_a_prefilled_quotation_form(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $tile = $this->product($user);

        $this->actingAs($user)
            ->post(route('quotations.draft'), [
                'items' => [
                    ['catalog_item_id' => $tile->id, 'quantity' => 12, 'rate' => 58],
                ],
            ])
            ->assertRedirect(route('invoices.create', ['document_type' => 'quotation']));

        $this->actingAs($user)
            ->get(route('invoices.create', ['document_type' => 'quotation']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/create')
                ->where('requestedDocumentType', 'quotation')
                ->has('draftItems', 1)
                ->where('draftItems.0.item_name', 'Vitrified Floor Tile 600x600')
                ->where('draftItems.0.quantity', 12)
                ->where('draftItems.0.rate', 58)
                ->where('draftItems.0.rate_type', 'per_sqft')
                ->where('draftItems.0.length', 60)
                ->where('draftItems.0.width', 60)
                ->where('draftItems.0.wastage_percent', 5)
            );
    }

    /**
     * The builder form names fields `items[<id>][…]`, so the payload arrives
     * with sparse, id-keyed indexes rather than a 0..n list. This guards the
     * browser/contract pairing — a `items[][catalog_item_id]` checkbox would
     * post under index 0 and silently drop the product.
     */
    public function test_the_draft_accepts_the_id_keyed_payload_the_builder_form_posts(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $first = $this->product($user, ['item_code' => 'TILE-A', 'name' => 'Tile A']);
        $second = $this->product($user, ['item_code' => 'TILE-B', 'name' => 'Tile B']);

        $this->actingAs($user)
            ->post(route('quotations.draft'), [
                'items' => [
                    $first->id => [
                        'catalog_item_id' => $first->id,
                        'quantity' => 12,
                        'rate' => 58,
                    ],
                    $second->id => [
                        'catalog_item_id' => $second->id,
                        'quantity' => 3,
                        'rate' => 61,
                    ],
                ],
            ])
            ->assertRedirect(route('invoices.create', ['document_type' => 'quotation']))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('invoices.create', ['document_type' => 'quotation']))
            ->assertInertia(fn ($page) => $page
                ->has('draftItems', 2)
                ->where('draftItems.0.item_name', 'Tile A')
                ->where('draftItems.0.catalog_item_id', $first->id)
                ->where('draftItems.0.rate', 58)
                ->where('draftItems.1.item_name', 'Tile B')
                ->where('draftItems.1.catalog_item_id', $second->id)
            );
    }

    public function test_the_draft_is_single_use(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $tile = $this->product($user);

        $this->actingAs($user)->post(route('quotations.draft'), [
            'items' => [['catalog_item_id' => $tile->id, 'quantity' => 1]],
        ]);

        $this->actingAs($user)->get(route('invoices.create'))->assertInertia(
            fn ($page) => $page->has('draftItems', 1),
        );

        // Second visit must not carry the previous selection.
        $this->actingAs($user)->get(route('invoices.create'))->assertInertia(
            fn ($page) => $page->has('draftItems', 0),
        );
    }

    public function test_the_preselected_quotation_can_be_saved(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $tile = $this->product($user);

        $this->actingAs($user)->post(route('quotations.draft'), [
            'items' => [['catalog_item_id' => $tile->id, 'quantity' => 10]],
        ]);

        $draft = session('quotation_draft')[0];

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $this->customerFor($user)->id,
            'document_type' => 'quotation',
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => $draft['item_name'],
                'catalog_item_id' => $draft['catalog_item_id'],
                'hsn_code' => $draft['hsn_code'],
                'quantity' => $draft['quantity'],
                'length' => $draft['length'],
                'width' => $draft['width'],
                'wastage_percent' => $draft['wastage_percent'],
                'rate_type' => $draft['rate_type'],
                'rate' => $draft['rate'],
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $invoice = Invoice::firstOrFail();
        $item = $invoice->items->firstOrFail();

        $this->assertSame('quotation', $invoice->document_type->value);
        $this->assertSame($tile->id, $item->catalog_item_id);
        // 60×60cm = 3.875 sq ft (929.0304 cm² per sq ft) × 1.05 wastage × ₹55 × 10
        $this->assertEquals(2237.82, $item->base_value);
    }

    public function test_a_draft_cannot_reference_another_tenants_product(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $other = $this->adminFor();
        $foreignProduct = $this->product($other);

        $this->actingAs($user)
            ->post(route('quotations.draft'), [
                'items' => [['catalog_item_id' => $foreignProduct->id, 'quantity' => 1]],
            ])
            ->assertSessionHasErrors('items.0.catalog_item_id');
    }

    public function test_a_draft_needs_at_least_one_product(): void
    {
        $user = $this->adminFor($this->tilesTenant());

        $this->actingAs($user)
            ->post(route('quotations.draft'), ['items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_inactive_products_are_not_offered_to_the_builder(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user, ['is_active' => false]);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('catalogProducts', 0));
    }

    public function test_active_products_are_offered_to_the_builder(): void
    {
        $user = $this->adminFor($this->tilesTenant());
        $this->product($user);

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('catalogProducts', 1)
                ->where('catalogProducts.0.name', 'Vitrified Floor Tile 600x600')
                ->where('catalogProducts.0.rate_type', 'per_sqft')
            );
    }
}
