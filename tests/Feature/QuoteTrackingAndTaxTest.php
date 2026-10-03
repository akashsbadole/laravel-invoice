<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\TaxMode;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use App\Services\InvoiceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTrackingAndTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_quote_view_and_download_logs_events(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'cgst_sgst',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Diamond Ring',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 20000,
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        // Generate share link via route.
        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();

        $shareLink = InvoiceShareLink::where('invoice_id', $quotation->id)->firstOrFail();

        // View public link
        $this->get(route('invoices.public.show', $shareLink->token))->assertOk();

        // Download public PDF
        $this->get(route('invoices.public.pdf', $shareLink->token))->assertOk();

        $shareLink->refresh();
        $this->assertNotNull($shareLink->viewed_at);
        $this->assertNotNull($shareLink->downloaded_at);

        $viewedEvent = InvoiceEvent::where('invoice_id', $quotation->id)
            ->where('event_type', InvoiceEventType::LinkViewed->value)
            ->first();

        $downloadedEvent = InvoiceEvent::where('invoice_id', $quotation->id)
            ->where('event_type', InvoiceEventType::LinkDownloaded->value)
            ->first();

        $this->assertNotNull($viewedEvent);
        $this->assertNotNull($downloadedEvent);
    }

    public function test_gst_tax_modes_calculate_properly(): void
    {
        $calculator = app(InvoiceCalculationService::class);

        // Intrastate CGST + SGST
        $cgstSgstResult = $calculator->calculate([
            'pricing_mode' => 'manual',
            'tax_mode' => TaxMode::CgstSgst->value,
            'items' => [[
                'item_name' => 'Hardware Tool',
                'quantity' => 2,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 18,
                'discount' => 0,
                'charges' => [],
            ]],
            'invoice_charges' => [],
        ]);

        $this->assertSame(2000.0, (float) $cgstSgstResult['subtotal']);
        $this->assertSame(360.0, (float) $cgstSgstResult['tax']);
        $this->assertCount(2, $cgstSgstResult['tax_breakdown']);
        $this->assertSame('CGST @ 9%', $cgstSgstResult['tax_breakdown'][0]['label']);
        $this->assertSame(180.0, (float) $cgstSgstResult['tax_breakdown'][0]['amount']);
        $this->assertSame('SGST @ 9%', $cgstSgstResult['tax_breakdown'][1]['label']);
        $this->assertSame(180.0, (float) $cgstSgstResult['tax_breakdown'][1]['amount']);

        // Interstate IGST
        $igstResult = $calculator->calculate([
            'pricing_mode' => 'manual',
            'tax_mode' => TaxMode::Igst->value,
            'items' => [[
                'item_name' => 'Hardware Tool',
                'quantity' => 2,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 18,
                'discount' => 0,
                'charges' => [],
            ]],
            'invoice_charges' => [],
        ]);

        $this->assertSame(360.0, (float) $igstResult['tax']);
        $this->assertCount(1, $igstResult['tax_breakdown']);
        $this->assertSame('IGST @ 18%', $igstResult['tax_breakdown'][0]['label']);
        $this->assertSame(360.0, (float) $igstResult['tax_breakdown'][0]['amount']);
    }

    public function test_documentation_page_is_accessible(): void
    {
        $this->get('/docs')->assertOk();
    }
}
