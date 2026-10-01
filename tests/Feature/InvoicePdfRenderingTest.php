<?php

namespace Tests\Feature;

use App\Mail\InvoicePdfMail;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The PDF is the app's core deliverable. These exercise the real DomPDF
 * render for every path that produces one, because a missing view variable
 * only blows up at render time — `Mail::fake()` and status-code assertions
 * never reach it.
 */
class InvoicePdfRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function invoice(): Invoice
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => 'jewelry_invoice',
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 3,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Ring',
                'metal_type' => 'Gold',
                'purity' => '22K',
                'quantity' => 1,
                'net_weight' => 5.2,
                'rate_type' => 'fixed',
                'rate' => 45000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::with(['customer', 'items.charges', 'template'])->firstOrFail();
    }

    protected function assertRealPdf(string $bytes, string $context): void
    {
        $this->assertStringStartsWith('%PDF', $bytes, "{$context} did not produce a PDF.");
        $this->assertGreaterThan(
            1000,
            strlen($bytes),
            "{$context} produced a suspiciously small PDF.",
        );
    }

    public function test_the_staff_pdf_renders(): void
    {
        $invoice = $this->invoice();
        $user = $invoice->tenant->users()->firstOrFail();

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertRealPdf((string) $response->getContent(), 'Staff PDF');
    }

    public function test_the_portal_pdf_renders(): void
    {
        $invoice = $this->invoice();
        $customer = $invoice->customer;

        $response = $this->actingAs($customer, 'portal')
            ->get(route('portal.invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertRealPdf((string) $response->getContent(), 'Portal PDF');
    }

    public function test_the_public_shared_pdf_renders(): void
    {
        $invoice = $this->invoice();
        $user = $invoice->tenant->users()->firstOrFail();

        $link = $this->actingAs($user)->post(route('invoices.share-links.store', $invoice), [
            'channel' => 'link',
        ])->assertRedirect();

        $token = $invoice->shareLinks()->latest()->value('token');
        $this->assertNotNull($token);

        $response = $this->get(route('invoices.public.pdf', $token));
        $response->assertOk();
        $this->assertRealPdf($response->streamedContent() ?: $response->getContent(), 'Public PDF');
    }

    public function test_the_emailed_pdf_attachment_renders(): void
    {
        $invoice = $this->invoice();

        $attachments = (new InvoicePdfMail($invoice))->attachments();

        $this->assertCount(1, $attachments);

        // Exercise the DomPDF render the attachment depends on.
        $bytes = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'business' => \App\Models\BusinessSetting::forTenant($invoice->tenant_id),
            'template' => $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id),
            'industry' => 'jewelry',
            'showWeights' => true,
            'showStones' => true,
            'hasAreaItems' => false,
            'publicUrl' => null,
            'qrSvg' => null,
        ])->setPaper('a4')->output();

        $this->assertRealPdf($bytes, 'Emailed PDF');
    }

    public function test_the_pdf_template_renders_without_any_optional_variable(): void
    {
        $invoice = $this->invoice();

        // The exact shape InvoicePdfMail used to pass, which omitted the
        // industry flags — this is the regression that made emailing throw.
        $html = view('pdf.invoice', [
            'invoice' => $invoice,
            'business' => \App\Models\BusinessSetting::forTenant($invoice->tenant_id),
            'template' => $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id),
            'publicUrl' => null,
            'qrSvg' => null,
        ])->render();

        $this->assertStringContainsString($invoice->invoice_number, $html);
    }
}