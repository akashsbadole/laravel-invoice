<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationChangesRequestedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_changes_request_keeps_the_quotation_open(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['quotation_customer_decisions' => true]);
        });

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
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

        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();
        $shareLink = InvoiceShareLink::where('invoice_id', $quotation->id)->firstOrFail();

        $response = $this->post(route('invoices.public.changes', $shareLink->token), [
            'response' => 'Can we do 20g instead of 15g?',
            'name' => 'Rahul',
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $quotation->refresh();

        // The negotiation stays alive: the status is untouched, but staff
        // now hold the customer's own words to work from.
        $this->assertNull($quotation->quotation_status);
        $this->assertSame('Can we do 20g instead of 15g? — Rahul', $quotation->quotation_response);
        $this->assertNotNull($quotation->quotation_responded_at);

        $event = InvoiceEvent::where('invoice_id', $quotation->id)
            ->get()
            ->first(fn (InvoiceEvent $event) => ($event->meta['action'] ?? null) === 'changes_requested');

        $this->assertNotNull($event);
        $this->assertSame('Can we do 20g instead of 15g? — Rahul', $event->meta['response']);
    }

    public function test_changes_request_needs_a_message(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['quotation_customer_decisions' => true]);
        });

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
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
        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();
        $shareLink = InvoiceShareLink::where('invoice_id', $quotation->id)->firstOrFail();

        $this->post(route('invoices.public.changes', $shareLink->token), ['response' => ''])
            ->assertSessionHasErrors(['response']);

        $this->assertNull($quotation->refresh()->quotation_response);
    }
}
