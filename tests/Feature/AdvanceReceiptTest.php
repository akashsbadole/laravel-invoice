<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvanceReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function invoice(float $grandTotal = 5000): Invoice
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'tax_rate' => 0,
            'discount' => 0,
            'invoice_charges' => [],
            'items' => [[
                'item_name' => 'Service line',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => $grandTotal,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        return Invoice::where('document_type', DocumentType::GeneralInvoice->value)->firstOrFail();
    }

    protected function recordAdvance(Invoice $invoice, float $amount, array $overrides = []): CustomerAdvance
    {
        return $invoice->customer->advances()->create(array_merge([
            'amount' => $amount,
            'applied_amount' => 0,
            'advance_date' => now()->subDays(10)->toDateString(),
            'payment_method' => 'upi',
            'tenant_id' => $invoice->tenant_id,
            'status' => 'available',
        ], $overrides));
    }

    protected function apply(Invoice $invoice, CustomerAdvance $advance, array $overrides = [])
    {
        return $this->actingAs($invoice->tenant->users()->first())->post(
            route('invoices.advances.apply', $invoice),
            array_merge(['advance_id' => $advance->id], $overrides),
        );
    }

    public function test_an_advance_can_be_recorded_for_a_customer(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('customers.advances.store', $invoice->customer_id), [
                'amount' => 3000,
                'advance_date' => now()->toDateString(),
                'payment_method' => 'upi',
                'reference_number' => 'UPI-8891',
                'notes' => 'Booking for ring',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $advance = CustomerAdvance::firstOrFail();

        $this->assertSame(3000.0, (float) $advance->amount);
        $this->assertSame('available', $advance->status->value);
        $this->assertSame($invoice->customer_id, $advance->customer_id);
    }

    public function test_the_customer_page_shows_the_available_advance(): void
    {
        $invoice = $this->invoice();
        $this->recordAdvance($invoice, 2500);
        $this->recordAdvance($invoice, 500, ['applied_amount' => 500, 'status' => 'applied']);

        $this->actingAs($invoice->tenant->users()->first())
            ->get(route('customers.show', $invoice->customer_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/show')
                ->has('customer.advances', 2)
                ->where('stats.available_advance', 2500)
            );
    }

    public function test_applying_an_advance_pays_the_invoice(): void
    {
        $invoice = $this->invoice(5000);
        $advance = $this->recordAdvance($invoice, 5000);

        $this->apply($invoice, $advance)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->balance_amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(5000.0, (float) $advance->refresh()->applied_amount);
        $this->assertSame('applied', $advance->status->value);

        $payment = $invoice->payments()->firstOrFail();
        // Dated to when the money was taken, not when it was consumed.
        $this->assertSame(
            $advance->advance_date->toDateString(),
            $payment->payment_date->toDateString(),
        );
        $this->assertSame('upi', $payment->payment_method->value);
    }

    public function test_a_partial_application_keeps_the_rest_available(): void
    {
        $invoice = $this->invoice(5000);
        $advance = $this->recordAdvance($invoice, 3000);

        $this->apply($invoice, $advance, ['amount' => 2000])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $advance->refresh();

        $this->assertSame(3000.0, (float) $invoice->balance_amount);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame(2000.0, (float) $advance->applied_amount);
        $this->assertSame(1000.0, $advance->availableAmount());
        $this->assertSame('available', $advance->status->value);
        $this->assertSame(1000.0, $invoice->customer->availableAdvance());
    }

    public function test_an_advance_cannot_be_applied_more_than_it_holds(): void
    {
        $invoice = $this->invoice(10000);
        $advance = $this->recordAdvance($invoice, 500);

        $this->apply($invoice, $advance, ['amount' => 800])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0.0, (float) $advance->refresh()->applied_amount);
        $this->assertSame(10000.0, (float) $invoice->fresh()->balance_amount);
    }

    public function test_applying_stops_at_the_invoice_balance(): void
    {
        $invoice = $this->invoice(1000);
        $advance = $this->recordAdvance($invoice, 5000);

        $this->apply($invoice, $advance)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->balance_amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        // 1000 consumed, 4000 still sitting on the advance.
        $this->assertSame(4000.0, $advance->refresh()->availableAmount());
        $this->assertSame('available', $advance->status->value);
    }

    public function test_an_advance_of_another_customer_cannot_be_applied(): void
    {
        $invoice = $this->invoice();
        $user = $invoice->tenant->users()->first();
        $other = $this->customerFor($user);
        $foreignAdvance = $other->advances()->create([
            'amount' => 500,
            'applied_amount' => 0,
            'advance_date' => now()->subDays(3)->toDateString(),
            'payment_method' => 'cash',
            'tenant_id' => $invoice->tenant_id,
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->post(route('invoices.advances.apply', $invoice), [
                'advance_id' => $foreignAdvance->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0.0, (float) $foreignAdvance->refresh()->applied_amount);
    }

    public function test_a_settled_invoice_refuses_more_advances(): void
    {
        $invoice = $this->invoice(1000);
        $advance = $this->recordAdvance($invoice, 1000);
        $this->apply($invoice, $advance);
        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->balance_amount);

        $second = $this->recordAdvance($invoice, 200);

        $this->apply($invoice, $second)
            ->assertSessionHasErrors('amount');

        $this->assertSame(0.0, (float) $second->refresh()->applied_amount);
    }

    public function test_an_unused_advance_can_be_refunded_but_a_used_one_cannot(): void
    {
        $invoice = $this->invoice();
        $fresh = $this->recordAdvance($invoice, 700);
        $used = $this->recordAdvance($invoice, 700);
        $user = $invoice->tenant->users()->first();

        $this->actingAs($user)
            ->post(route('customers.advances.refund', [$invoice->customer_id, $fresh->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('refunded', $fresh->refresh()->status->value);

        // The used portion is settled against an invoice and stays there.
        $this->apply($invoice, $used);

        $this->actingAs($user)
            ->post(route('customers.advances.refund', [$invoice->customer_id, $used->id]))
            ->assertStatus(422);

        $this->assertSame('applied', $used->refresh()->status->value);
    }

    public function test_a_refunded_advance_leaves_the_available_balance(): void
    {
        $invoice = $this->invoice();
        $advance = $this->recordAdvance($invoice, 900);

        $this->assertSame(900.0, $invoice->customer->availableAdvance());

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('customers.advances.refund', [$invoice->customer_id, $advance->id]))
            ->assertRedirect();

        $this->assertSame(0.0, $invoice->customer->fresh()->availableAdvance());
    }

    public function test_the_invoice_page_lists_open_advances_for_the_picker(): void
    {
        $invoice = $this->invoice();
        $open = $this->recordAdvance($invoice, 1500);
        $this->recordAdvance($invoice, 400, ['applied_amount' => 400, 'status' => 'applied']);

        $this->actingAs($invoice->tenant->users()->first())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/show')
                ->has('availableAdvances', 1)
                ->where('availableAdvances.0.id', $open->id)
            );
    }

    public function test_a_viewer_cannot_record_or_apply_advances(): void
    {
        $invoice = $this->invoice();
        $advance = $this->recordAdvance($invoice, 100);
        $viewer = $this->userWithRole(UserRole::Viewer, $invoice->tenant);

        $this->actingAs($viewer)
            ->post(route('customers.advances.store', $invoice->customer_id), [
                'amount' => 100,
                'advance_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('invoices.advances.apply', $invoice), [
                'advance_id' => $advance->id,
            ])
            ->assertForbidden();
    }

    public function test_applying_an_advance_settles_the_oldest_installment_first(): void
    {
        $invoice = $this->invoice(3000);
        $advance = $this->recordAdvance($invoice, 3000);

        $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.installments.store', $invoice), [
                'installments' => [
                    ['due_date' => now()->addDays(5)->toDateString(), 'amount' => 1000],
                    ['due_date' => now()->addDays(35)->toDateString(), 'amount' => 2000],
                ],
            ])
            ->assertRedirect();

        $this->apply($invoice, $advance)->assertRedirect();

        $statuses = $invoice->installments()
            ->orderBy('sequence')
            ->get()
            ->map(fn ($installment) => $installment->status->value)
            ->all();

        $this->assertSame(['paid', 'paid'], $statuses);
    }
}
