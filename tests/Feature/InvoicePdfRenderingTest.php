<?php

namespace Tests\Feature;

use App\Mail\InvoicePdfMail;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoicePdfService;
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

        // The portal guards on a session key, not on staff auth.
        $this->withSession(['portal_customer_id' => $invoice->customer_id]);

        $response = $this->get(route('portal.invoices.pdf', $invoice));

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
        $this->assertRealPdf((string) $response->getContent(), 'Public PDF');
    }

    public function test_the_emailed_pdf_attachment_renders(): void
    {
        $invoice = $this->invoice();
        $mailable = new InvoicePdfMail($invoice);

        $this->assertCount(1, $mailable->attachments());

        // Render the whole mailable: body, markdown/html template and the
        // DomPDF attachment, exactly as a real send would.
        $rendered = $mailable->render();

        $this->assertStringContainsString($invoice->invoice_number, $rendered);
        $this->assertStringContainsString(
            BusinessSetting::forTenant($invoice->tenant_id)->default_currency,
            $rendered,
        );

        // Exercise the DomPDF render the attachment depends on.
        $bytes = Pdf::loadView('pdf.invoice', app(InvoicePdfService::class)
            ->viewData($invoice, withShareLink: false))
            ->setPaper('a4')
            ->output();

        $this->assertRealPdf($bytes, 'Emailed PDF');
    }

    /**
     * The template reads these unconditionally, so the shared assembler is the
     * only thing standing between a new call site and a 500.
     */
    public function test_the_assembler_supplies_every_variable_the_template_reads(): void
    {
        $invoice = $this->invoice();

        $data = app(InvoicePdfService::class)->viewData($invoice);

        foreach ([
            'invoice', 'business', 'template', 'industry',
            'showWeights', 'showStones', 'hasAreaItems', 'publicUrl', 'qrSvg',
        ] as $key) {
            $this->assertArrayHasKey($key, $data, "pdf.invoice needs \${$key}.");
        }

        $this->assertIsBool($data['showWeights']);
        $this->assertIsBool($data['showStones']);
        $this->assertIsBool($data['hasAreaItems']);
    }

    public function test_the_template_renders_for_a_non_weight_industry(): void
    {
        $user = $this->adminFor(Tenant::factory()->forIndustry('tiles_marble')->create());
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => 'general_invoice',
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Vitrified Tile',
                'quantity' => 10,
                'length' => 60,
                'width' => 60,
                'rate_type' => 'per_sqft',
                'rate' => 55,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $invoice = Invoice::with(['customer', 'items.charges', 'template'])->firstOrFail();
        $data = app(InvoicePdfService::class)->viewData($invoice);

        $this->assertFalse($data['showWeights'], 'Tiles invoices must not show weight columns.');
        $this->assertTrue($data['hasAreaItems'], 'Rows with length/width should report area.');

        $html = view('pdf.invoice', $data)->render();
        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringNotContainsString('Metal / Purity', $html);
    }
}
