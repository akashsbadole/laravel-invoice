<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\RateLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_quotation_defaults_rate_locked_at_to_its_invoice_date(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);
        $invoiceDate = now()->subDays(2)->toDateString();

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => $invoiceDate,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Necklace',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7150,
                'net_weight' => 15.2,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->assertSame($invoiceDate, $quotation->rate_locked_at->toDateString());
    }

    public function test_staff_can_override_the_rate_lock_date(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'rate_locked_at' => now()->subDays(3)->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Silver Ring',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 90,
                'net_weight' => 5,
                'metal_type' => 'Silver',
                'purity' => '925',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->assertSame(now()->subDays(3)->toDateString(), $quotation->rate_locked_at->toDateString());
    }

    public function test_conversion_keeps_the_quotation_rate_lock(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->subDays(5)->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Ring',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7000,
                'net_weight' => 4,
                'metal_type' => 'Gold',
                'purity' => '18K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();
        $locked = $quotation->rate_locked_at->toDateString();

        // Advance "today" past the quotation's own date so inheritance is
        // the only way the new invoice could carry the same stamp.
        $this->travelTo(now());
        $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::JewelryInvoice->value,
        ])->assertRedirect();

        $invoice = Invoice::where('document_type', DocumentType::JewelryInvoice->value)->firstOrFail();

        $this->assertSame($locked, $invoice->rate_locked_at->toDateString());
        $this->assertNotSame(now()->toDateString(), $invoice->rate_locked_at->toDateString());
    }

    public function test_resaving_a_quotation_keeps_its_original_rate_lock(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->subDays(4)->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Coin',
                'quantity' => 1,
                'rate_type' => 'per_gram',
                'rate' => 7200,
                'net_weight' => 10,
                'metal_type' => 'Gold',
                'purity' => '24K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();
        $originalLock = $quotation->rate_locked_at->toDateString();

        $this->actingAs($user)->put(route('invoices.update', $quotation), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => $originalLock,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Coin',
                'quantity' => 2,
                'rate_type' => 'per_gram',
                'rate' => 7200,
                'net_weight' => 10,
                'metal_type' => 'Gold',
                'purity' => '24K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $this->assertSame($originalLock, $quotation->refresh()->rate_locked_at->toDateString());
    }

    public function test_rate_lock_service_flags_stale_metal_prices(): void
    {
        $quotation = new Invoice([
            'invoice_date' => now()->subDays(10)->toDateString(),
            'rate_locked_at' => now()->subDays(10)->toDateString(),
        ]);
        $quotation->setRelation('items', collect([
            new InvoiceItem(['metal_type' => 'Gold', 'purity' => '22K']),
        ]));

        $service = app(RateLockService::class);

        $this->assertTrue($service->appliesTo($quotation));
        $this->assertSame(10, $service->ageInDays($quotation));
        $this->assertTrue($service->isStale($quotation));
        $this->assertFalse($service->isStale($quotation, threshold: 30));
    }

    public function test_rate_lock_service_ignores_non_metal_documents(): void
    {
        $invoice = new Invoice([
            'invoice_date' => now()->subDays(10)->toDateString(),
            'rate_locked_at' => now()->subDays(10)->toDateString(),
        ]);
        $invoice->setRelation('items', collect([
            new InvoiceItem(['metal_type' => null, 'purity' => null, 'length' => 2, 'width' => 2]),
        ]));

        $service = app(RateLockService::class);

        $this->assertFalse($service->appliesTo($invoice));
        $this->assertNull($service->ageInDays($invoice));
        $this->assertFalse($service->isStale($invoice));
    }
}
