<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceCreationByIndustryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function jewelryItem(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Gold Ring',
            'quantity' => 1,
            'metal_type' => 'Gold',
            'purity' => '22K',
            'net_weight' => 10,
            'rate_type' => 'per_gram',
            'rate' => 5000,
            'tax_rate' => 3,
            'discount' => 0,
            'charges' => [],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'due_date' => null,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 3,
            'discount' => 0,
            'invoice_charges' => [],
        ], $overrides);
    }

    public function test_a_jewelry_tenant_can_create_a_jewelry_invoice(): void
    {
        $user = $this->adminFor($this->jewelryTenant());
        $customer = $this->customerFor($user);

        $response = $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::JewelryInvoice->value,
            'pricing_mode' => 'jewelry_calculated',
            'items' => [$this->jewelryItem()],
        ]));

        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'document_type' => DocumentType::JewelryInvoice->value,
            'customer_id' => $customer->id,
        ]);

        $invoice = Invoice::firstOrFail();
        $this->assertEquals(50000, $invoice->items->first()->base_value);
    }

    public function test_a_tiles_tenant_can_bill_per_square_foot(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => 'Vitrified Tile 600x600',
                'quantity' => 10,
                'length' => 60,
                'width' => 60,
                'rate_type' => 'per_sqft',
                'rate' => 50,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        // 3.875 sq ft × ₹50 × 10
        $this->assertEquals(1937.5, Invoice::firstOrFail()->items->first()->base_value);
    }

    public function test_a_tiles_tenant_stores_dimensions_and_wastage(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => 'Marble Slab',
                'quantity' => 1,
                'length' => 300,
                'width' => 150,
                'wastage_percent' => 5,
                'size_label' => '600x300',
                'rate_type' => 'per_sqm',
                'rate' => 1200,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        $item = Invoice::firstOrFail()->items->first();

        // Dimension columns are decimal(10,3), so Eloquent returns strings.
        $this->assertSame('300.000', $item->length);
        $this->assertSame('150.000', $item->width);
        $this->assertSame('5.00', $item->wastage_percent);
        $this->assertSame('600x300', $item->size_label);
    }

    public function test_a_hardware_tenant_can_bill_per_piece_with_brand_and_warranty(): void
    {
        $user = $this->adminFor($this->tenantFor('hardware'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => 'Ball Valve 2 inch',
                'quantity' => 4,
                'brand' => 'Jindal',
                'model_number' => 'BV-200',
                'serial_number' => 'SN-9001',
                'warranty_months' => 24,
                'rate_type' => 'per_piece',
                'rate' => 750,
                'tax_rate' => 18,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        $item = Invoice::firstOrFail()->items->first();

        $this->assertEquals('Jindal', $item->brand);
        $this->assertEquals('BV-200', $item->model_number);
        $this->assertEquals('SN-9001', $item->serial_number);
        $this->assertEquals(24, $item->warranty_months);
        $this->assertEquals(3000, $item->base_value);
    }

    public function test_custom_attributes_round_trip_through_an_invoice(): void
    {
        $user = $this->adminFor($this->tenantFor('plumbing_electrical'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => 'Copper Wire 2.5mm',
                'quantity' => 2,
                'rate_type' => 'per_meter',
                'rate' => 45,
                'length' => 90,
                'attributes' => ['core' => 'annealed', 'colour' => 'red'],
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        $item = Invoice::firstOrFail()->items->first();

        // 90m × ₹45 × 2
        $this->assertEquals(8100, $item->base_value);
        $this->assertSame(['core' => 'annealed', 'colour' => 'red'], $item->attributes);
    }

    public function test_a_rate_type_outside_the_industrys_allowance_is_rejected(): void
    {
        $user = $this->adminFor($this->tenantFor('general'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => 'Mystery item',
                'quantity' => 1,
                // General trade cannot bill per gram.
                'rate_type' => 'per_gram',
                'rate' => 100,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertSessionHasErrors('items.0.rate_type');

        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());
    }

    public function test_picking_a_catalog_item_links_the_invoice_line_back_to_it(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));
        $customer = $this->customerFor($user);

        $catalogItem = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Vitrified Floor Tile',
            'brand' => 'Nirogran',
            'size_label' => '600x600',
            'rate_type' => 'per_sqft',
            'default_rate' => 55,
            'default_length' => 60,
            'default_width' => 60,
            'status' => 'active',
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'items' => [[
                'item_name' => $catalogItem->name,
                'catalog_item_id' => $catalogItem->id,
                'quantity' => 5,
                'length' => 60,
                'width' => 60,
                'rate_type' => 'per_sqft',
                'rate' => 55,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        $this->assertDatabaseHas('invoice_items', [
            'catalog_item_id' => $catalogItem->id,
            'item_name' => 'Vitrified Floor Tile',
        ]);

        $this->assertTrue($catalogItem->invoiceItems()->exists());
    }

    public function test_a_non_jewelry_tenant_cannot_open_the_metal_rates_screen(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));

        $this->actingAs($user)
            ->get(route('metal-rates.index'))
            ->assertNotFound();
    }

    public function test_a_jewelry_tenant_can_still_open_the_metal_rates_screen(): void
    {
        $user = $this->adminFor($this->jewelryTenant());

        $this->actingAs($user)
            ->get(route('metal-rates.index'))
            ->assertOk();
    }

    public function test_quotation_can_be_created_and_converted_to_an_invoice(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->invoicePayload([
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'items' => [[
                'item_name' => 'Tile supply',
                'quantity' => 100,
                'rate_type' => 'per_sqft',
                'rate' => 50,
                'length' => 60,
                'width' => 60,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ]))->assertRedirect();

        $quotation = Invoice::firstOrFail();
        $this->assertSame(DocumentType::Quotation, $quotation->document_type);
        $this->assertSame(InvoiceStatus::Draft->value, $quotation->status->value);
        $this->assertStringStartsWith(config('app.quotation_prefix') ?? 'QT', $quotation->invoice_number);

        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame(InvoiceStatus::Converted->value, $quotation->status->value);
        $this->assertNotNull($quotation->converted_to_id);

        $invoice = $quotation->convertedTo;
        $this->assertSame(DocumentType::GeneralInvoice, $invoice->document_type);
        $this->assertEquals($quotation->grand_total, $invoice->grand_total);
        $this->assertEquals(1, $invoice->items()->count());
    }

    public function test_the_create_form_defaults_to_the_industrys_document_type(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));

        $this->actingAs($user)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/create')
                ->where('industryConfig.key', 'tiles_marble')
                ->where('industryConfig.uses_metal_rates', false)
                ->where('requestedDocumentType', DocumentType::GeneralInvoice->value)
            );
    }

    public function test_a_quotation_can_be_preselected_from_the_list_page(): void
    {
        $user = $this->adminFor($this->tenantFor('tiles_marble'));

        $this->actingAs($user)
            ->get(route('invoices.create', ['document_type' => 'quotation']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requestedDocumentType', DocumentType::Quotation->value)
            );
    }

    protected function tenantFor(string $industry): Tenant
    {
        return Tenant::factory()->forIndustry($industry)->create();
    }

    protected function jewelryTenant(): Tenant
    {
        return $this->tenantFor('jewelry');
    }
}
