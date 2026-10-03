<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $customerId, string $date, int $quantity = 1): array
    {
        return [
            'customer_id' => $customerId,
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => $date,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Gold Necklace',
                'quantity' => $quantity,
                'rate_type' => 'per_gram',
                'rate' => 7150,
                'net_weight' => 15.2,
                'metal_type' => 'Gold',
                'purity' => '22K',
                'tax_rate' => 3,
                'discount' => 0,
                'charges' => [],
            ]],
        ];
    }

    public function test_a_new_quotation_starts_at_revision_one(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer->id, now()->toDateString()))
            ->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->assertSame(1, $quotation->revision_number);
        $this->assertNull($quotation->revision_note);
    }

    public function test_editing_a_draft_keeps_revision_one(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer->id, now()->toDateString()))
            ->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)->put(route('invoices.update', $quotation), $this->payload($customer->id, $quotation->invoice_date->toDateString(), 2))
            ->assertRedirect();

        $this->assertSame(1, $quotation->refresh()->revision_number);
        $this->assertNull($quotation->revision_note);
    }

    public function test_editing_a_sent_quotation_bumps_the_revision_and_records_the_reason(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer->id, now()->toDateString()))
            ->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), ['status' => 'sent'])
            ->assertRedirect();

        $this->actingAs($user)->put(route('invoices.update', $quotation), [
            ...$this->payload($customer->id, now()->toDateString(), 3),
            'revision_note' => 'Customer asked for 20g instead of 15g',
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame(2, $quotation->revision_number);
        $this->assertSame('Customer asked for 20g instead of 15g', $quotation->revision_note);

        $event = InvoiceEvent::where('invoice_id', $quotation->id)
            ->get()
            ->first(fn (InvoiceEvent $event) => ($event->meta['action'] ?? null) === 'revision');

        $this->assertNotNull($event);
        $this->assertSame(2, $event->meta['revision']);
        $this->assertSame('Customer asked for 20g instead of 15g', $event->meta['note']);
    }

    public function test_a_sent_quotation_edit_without_a_reason_uses_a_default_note(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer->id, now()->toDateString()))
            ->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), ['status' => 'sent'])
            ->assertRedirect();

        $this->actingAs($user)->put(route('invoices.update', $quotation), $this->payload($customer->id, now()->toDateString(), 2))
            ->assertRedirect();

        $this->assertSame(2, $quotation->refresh()->revision_number);
        $this->assertSame('Updated after sending to customer', $quotation->revision_note);
    }

    public function test_repeat_edits_advance_the_revision_monotonically(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), $this->payload($customer->id, now()->toDateString()))
            ->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)->post(route('invoices.quotation-status', $quotation), ['status' => 'sent'])
            ->assertRedirect();

        $this->actingAs($user)->put(route('invoices.update', $quotation), [
            ...$this->payload($customer->id, now()->toDateString(), 2),
            'revision_note' => 'First amendment',
        ])->assertRedirect();

        $this->actingAs($user)->put(route('invoices.update', $quotation), [
            ...$this->payload($customer->id, now()->toDateString(), 4),
            'revision_note' => 'Second amendment',
        ])->assertRedirect();

        $this->assertSame(3, $quotation->refresh()->revision_number);
        $this->assertSame('Second amendment', $quotation->revision_note);
    }

    public function test_an_invoice_that_is_not_a_quotation_never_bumps(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $payload = $this->payload($customer->id, now()->toDateString());
        unset($payload['document_type']);
        $payload['document_type'] = DocumentType::JewelryInvoice->value;

        $this->actingAs($user)->post(route('invoices.store'), $payload)->assertRedirect();

        $invoice = Invoice::where('document_type', DocumentType::JewelryInvoice->value)->firstOrFail();

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            ...$payload,
            'items' => [[
                'item_name' => 'Gold Necklace',
                'quantity' => 2,
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

        $this->assertSame(1, $invoice->refresh()->revision_number);
    }
}
