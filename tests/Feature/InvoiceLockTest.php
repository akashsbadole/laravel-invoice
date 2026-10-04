<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLockTest extends TestCase
{
    use RefreshDatabase;

    protected function createInvoiceFor($user, array $attributes = []): Invoice
    {
        $customer = $this->customerFor($user);

        return $this->inTenant($user, fn () => Invoice::create(array_merge([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'status' => 'unpaid',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'balance_amount' => 1000,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'created_by' => $user->id,
        ], $attributes)));
    }

    public function test_paid_invoice_cannot_be_edited_or_deleted(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createInvoiceFor($user, ['paid_amount' => 500, 'balance_amount' => 500]);

        $this->actingAs($user)
            ->putJson(route('invoices.update', $invoice), [
                'customer_id' => $invoice->customer_id,
                'document_type' => DocumentType::GeneralInvoice->value,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'items' => [[
                    'item_name' => 'Item',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 1000,
                ]],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('invoices.destroy', $invoice))
            ->assertStatus(422);
    }

    public function test_einvoiced_invoice_cannot_be_edited_or_deleted(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createInvoiceFor($user, ['irn' => 'sample-irn-123']);

        $this->actingAs($user)
            ->putJson(route('invoices.update', $invoice), [
                'customer_id' => $invoice->customer_id,
                'document_type' => DocumentType::GeneralInvoice->value,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'items' => [[
                    'item_name' => 'Item',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 1000,
                ]],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('invoices.destroy', $invoice))
            ->assertStatus(422);
    }

    public function test_shared_or_sent_invoice_cannot_be_edited_or_deleted(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createInvoiceFor($user);

        \App\Models\InvoiceShareLink::forceCreate([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'created_by' => $user->id,
            'token' => 'test-token-123',
        ]);

        $this->actingAs($user)
            ->putJson(route('invoices.update', $invoice), [
                'customer_id' => $invoice->customer_id,
                'document_type' => DocumentType::GeneralInvoice->value,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'items' => [[
                    'item_name' => 'Item',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 1000,
                ]],
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->deleteJson(route('invoices.destroy', $invoice))
            ->assertStatus(422);
    }

    public function test_invoice_cannot_be_retyped_into_a_credit_note(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createInvoiceFor($user);

        $this->actingAs($user)
            ->putJson(route('invoices.update', $invoice), [
                'customer_id' => $invoice->customer_id,
                'document_type' => DocumentType::CreditNote->value,
                'invoice_date' => now()->toDateString(),
                'pricing_mode' => 'manual',
                'tax_mode' => 'single',
                'items' => [[
                    'item_name' => 'Item',
                    'quantity' => 1,
                    'rate_type' => 'fixed',
                    'rate' => 1000,
                ]],
            ])
            ->assertStatus(422);
    }
}
