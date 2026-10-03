<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceShareLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotationConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_can_be_converted_to_invoice_with_custom_due_date_and_advances(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        // Record a customer advance first.
        $this->inTenant($user, function () use ($customer, $user) {
            $customer->advances()->create([
                'amount' => 1000,
                'advance_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'notes' => 'Booking deposit',
                'created_by' => $user->id,
            ]);
        });

        // Create a quotation.
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
                'item_name' => 'Custom Jewelry Work',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 5000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();
        $dueDate = now()->addDays(10)->toDateString();

        // Convert the quotation to a sales invoice with advance applied.
        $response = $this->actingAs($user)->post(route('invoices.convert', $quotation), [
            'document_type' => DocumentType::GeneralInvoice->value,
            'due_date' => $dueDate,
            'apply_advances' => 1,
        ]);

        $response->assertRedirect();

        $convertedInvoice = Invoice::where('document_type', DocumentType::GeneralInvoice->value)->firstOrFail();
        $quotation->refresh();

        $this->assertSame(InvoiceStatus::Converted, $quotation->status);
        $this->assertSame($convertedInvoice->id, $quotation->converted_to_id);
        $this->assertSame($dueDate, $convertedInvoice->due_date->toDateString());
        $this->assertSame(1000.0, (float) $convertedInvoice->paid_amount);
        $this->assertSame(4000.0, (float) $convertedInvoice->balance_amount);
    }

    public function test_public_customer_can_accept_quotation_and_it_shows_on_dashboard_and_pipeline(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->inTenant($user, function () use ($user) {
            BusinessSetting::forTenant($user->tenant_id)->update(['quotation_customer_decisions' => true]);
        });

        // Create quotation.
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
                'item_name' => 'Gold Ring Design',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 15000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        // Generate share link using the controller route.
        $this->actingAs($user)->post(route('invoices.share-links.store', $quotation))->assertRedirect();

        $shareLink = InvoiceShareLink::where('invoice_id', $quotation->id)->firstOrFail();

        // Customer accepts from public link.
        $this->post(route('invoices.public.decide', $shareLink->token), [
            'decision' => QuotationStatus::Accepted->value,
            'name' => 'John Doe',
            'response' => 'Looks great, please proceed!',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Accepted, $quotation->quotation_status);

        // Verify dashboard shows the accepted quote ready to convert.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('acceptedQuotationsToConvert', 1)
                ->where('acceptedQuotationsToConvert.0.id', $quotation->id)
            );

        // Verify pipeline shows the accepted quote.
        $this->actingAs($user)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotations/index')
                ->has('acceptedQuotations', 1)
                ->where('acceptedQuotations.0.id', $quotation->id)
                ->where('analytics.accepted_count', 1)
            );
    }
}
