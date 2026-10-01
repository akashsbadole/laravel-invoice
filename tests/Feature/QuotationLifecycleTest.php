<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use App\Models\Tenant;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    /** The staff user who owns the quotation created by quotation(). */
    protected function user(): User
    {
        return $this->staff ??= $this->adminFor();
    }

    protected function quotation(): Invoice
    {
        $this->staff = $this->adminFor(Tenant::factory()->forIndustry('tiles_marble')->create());
        $customer = $this->customerFor($this->staff);

        $this->actingAs($this->staff)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Tile supply',
                'quantity' => 100,
                'rate_type' => 'per_piece',
                'rate' => 500,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::firstOrFail();
    }

    protected function shareLink(Invoice $quotation): InvoiceShareLink
    {
        $link = new InvoiceShareLink([
            'invoice_id' => $quotation->id,
            'is_active' => true,
        ]);
        $link->token = InvoiceShareLink::generateToken();
        $link->save();

        return $link;
    }

    public function test_a_new_quotation_starts_as_draft(): void
    {
        $this->assertSame(QuotationStatus::Draft, app(QuotationService::class)->currentStatus($this->quotation()));
    }

    public function test_staff_can_mark_a_quotation_as_sent(): void
    {
        $quotation = $this->quotation();

        $this->actingAs($this->user())
            ->post(route('invoices.quotation-status', $quotation), [
                'status' => QuotationStatus::Sent->value,
                'note' => 'Sent on WhatsApp',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Sent, $quotation->quotation_status);
        $this->assertSame(InvoiceStatus::Sent, $quotation->status);
        $this->assertDatabaseHas('invoice_events', [
            'invoice_id' => $quotation->id,
            'event_type' => 'updated',
        ]);
    }

    public function test_a_decided_quotation_cannot_be_reopened_by_staff(): void
    {
        $quotation = $this->quotation();

        $this->actingAs($this->user())->post(route('invoices.quotation-status', $quotation), [
            'status' => QuotationStatus::Rejected->value,
        ])->assertRedirect();

        $this->actingAs($this->user())
            ->post(route('invoices.quotation-status', $quotation), [
                'status' => QuotationStatus::Sent->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(QuotationStatus::Rejected, $quotation->refresh()->quotation_status);
    }

    public function test_a_customer_can_accept_from_the_shared_link_when_enabled(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
        ]);

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Accepted->value,
            'name' => 'Ravi Patel',
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Accepted, $quotation->quotation_status);
        $this->assertSame(InvoiceStatus::Accepted, $quotation->status);
        $this->assertStringContainsString('Ravi Patel', (string) $quotation->quotation_response);
        $this->assertNotNull($quotation->quotation_responded_at);
    }

    public function test_a_customer_can_decline_with_a_reason(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
        ]);

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Rejected->value,
            'response' => 'Too expensive, please revise',
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Rejected, $quotation->quotation_status);
        $this->assertSame('Too expensive, please revise', $quotation->quotation_response);
    }

    public function test_customer_decisions_are_blocked_when_the_setting_is_off(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        // quotation_customer_decisions defaults to false.
        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Accepted->value,
        ])->assertNotFound();

        $this->assertNull($quotation->refresh()->quotation_status);
    }

    public function test_a_customer_cannot_decide_twice(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
        ]);

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Accepted->value,
        ])->assertRedirect();

        $this->post(route('invoices.public.decide', $link->token), [
            'decision' => QuotationStatus::Rejected->value,
        ])->assertSessionHasErrors('decision');

        $this->assertSame(QuotationStatus::Accepted, $quotation->refresh()->quotation_status);
    }

    public function test_a_sales_invoice_has_no_quotation_lifecycle(): void
    {
        $this->staff = $this->adminFor();
        $customer = $this->customerFor($this->staff);

        $this->actingAs($this->user())->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Work',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 1000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();

        $this->actingAs($this->user())
            ->post(route('invoices.quotation-status', $invoice), [
                'status' => QuotationStatus::Sent->value,
            ])
            ->assertNotFound();
    }

    public function test_converting_marks_the_quotation_converted(): void
    {
        $quotation = $this->quotation();

        $this->actingAs($this->user())->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame(QuotationStatus::Converted, $quotation->quotation_status);
        $this->assertNotNull($quotation->converted_to_id);
    }

    public function test_the_shared_page_exposes_the_quotation_state_and_updates(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_customer_decisions' => true,
            'quotation_show_updates' => true,
        ]);

        $this->actingAs($this->user())->post(route('invoices.quotation-status', $quotation), [
            'status' => QuotationStatus::Sent->value,
            'note' => 'Sent on WhatsApp',
        ]);

        $this->get(route('invoices.public.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/public')
                ->where('quotation.status', QuotationStatus::Sent->value)
                ->where('quotation.can_decide', true)
                ->has('quotation.updates')
            );
    }

    public function test_the_updates_feed_can_be_hidden(): void
    {
        $quotation = $this->quotation();
        $link = $this->shareLink($quotation);

        BusinessSetting::forTenant($quotation->tenant_id)->update([
            'quotation_show_updates' => false,
        ]);

        $this->get(route('invoices.public.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('quotation.updates', 0));
    }
}
