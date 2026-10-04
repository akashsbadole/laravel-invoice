<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerPortalFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_portal_hides_draft_quotations(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        // Draft quotation (unreleased)
        $draft = $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'Q-0001',
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'status' => InvoiceStatus::Draft->value,
            'quotation_status' => QuotationStatus::Draft->value,
            'subtotal' => 5000,
            'grand_total' => 5000,
            'paid_amount' => 0,
            'balance_amount' => 5000,
            'created_by' => $user->id,
        ]));

        // Sent quotation
        $sent = $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'Q-0002',
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'status' => InvoiceStatus::Sent->value,
            'quotation_status' => QuotationStatus::Sent->value,
            'subtotal' => 10000,
            'grand_total' => 10000,
            'paid_amount' => 0,
            'balance_amount' => 10000,
            'created_by' => $user->id,
        ]));

        $this->withSession(['portal_customer_id' => $customer->id])
            ->get(route('portal.quotations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/quotations')
                ->has('quotations', 1)
                ->where('quotations.0.invoice_number', 'Q-0002')
            );
    }

    public function test_money_owed_excludes_quotations_and_challans(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        // Real invoice
        $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-0001',
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'status' => InvoiceStatus::Unpaid->value,
            'subtotal' => 5000,
            'grand_total' => 5000,
            'paid_amount' => 0,
            'balance_amount' => 5000,
            'created_by' => $user->id,
        ]));

        // Quotation
        $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'Q-0001',
            'document_type' => DocumentType::Quotation->value,
            'invoice_date' => now()->toDateString(),
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => 20000,
            'grand_total' => 20000,
            'paid_amount' => 0,
            'balance_amount' => 20000,
            'created_by' => $user->id,
        ]));

        // Delivery Challan
        $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'DC-0001',
            'document_type' => DocumentType::DeliveryChallan->value,
            'invoice_date' => now()->toDateString(),
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => 15000,
            'grand_total' => 15000,
            'paid_amount' => 0,
            'balance_amount' => 15000,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user);
        $this->assertEquals('5000', $customer->totalOutstanding());
    }
}
