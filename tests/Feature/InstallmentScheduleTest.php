<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallmentScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function invoice(float $grandTotal = 9000): Invoice
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
                'item_name' => 'Countertop slab',
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

    /**
     * @param  list<array{due_date:string, amount:string}>  $slices
     */
    protected function schedule(Invoice $invoice, array $slices)
    {
        return $this->actingAs($invoice->tenant->users()->first())
            ->post(route('invoices.installments.store', $invoice), [
                'installments' => array_map(
                    fn (array $slice) => [...$slice, 'notes' => null],
                    $slices,
                ),
            ]);
    }

    public function test_a_plan_splits_the_balance_into_dated_slices(): void
    {
        $invoice = $this->invoice(9000);

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => '3000.00'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $installments = $invoice->refresh()->installments;

        $this->assertCount(3, $installments);
        $this->assertSame([1, 2, 3], $installments->pluck('sequence')->all());
        $this->assertSame('3000.00', $installments->first()->amount);
        $this->assertSame(InstallmentStatus::Pending, $installments->first()->status);
    }

    public function test_a_plan_that_does_not_match_the_balance_is_rejected(): void
    {
        $invoice = $this->invoice(9000);

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '3000.00'],
        ])->assertSessionHasErrors('installments');

        $this->assertCount(0, $invoice->installments);
    }

    public function test_a_plan_with_repeated_due_dates_is_rejected(): void
    {
        $invoice = $this->invoice(9000);
        $sameDay = now()->toDateString();

        $this->schedule($invoice, [
            ['due_date' => $sameDay, 'amount' => '4500.00'],
            ['due_date' => $sameDay, 'amount' => '4500.00'],
        ])->assertSessionHasErrors('installments');
    }

    public function test_collecting_an_installment_records_a_payment_and_settles_it(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $installment = $invoice->installments()->firstOrFail();

        $this->actingAs($user)
            ->post(route('invoices.installments.collect', [$invoice, $installment]))
            ->assertRedirect();

        $installment->refresh();
        $invoice->refresh();

        $this->assertSame(InstallmentStatus::Paid, $installment->status);
        $this->assertNotNull($installment->paid_at);
        $this->assertNotNull($installment->payment_id);
        $this->assertSame('3000.00', $invoice->paid_amount);
        $this->assertSame('6000.00', $invoice->balance_amount);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_an_installment_cannot_be_collected_twice(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '9000.00'],
        ])->assertRedirect();

        $installment = $invoice->installments()->firstOrFail();

        $this->actingAs($user)->post(route('invoices.installments.collect', [$invoice, $installment]));
        $this->actingAs($user)
            ->post(route('invoices.installments.collect', [$invoice, $installment]))
            ->assertStatus(422);

        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_a_manual_payment_settles_the_oldest_pending_slice(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
            'amount' => 3000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'upi',
        ])->assertRedirect();

        $this->assertSame(
            InstallmentStatus::Paid,
            $invoice->installments()->firstOrFail()->status,
        );
        // The relation already orders by sequence, so ask for the last row
        // by sequence rather than re-sorting.
        $this->assertSame(
            InstallmentStatus::Pending,
            Installment::where('invoice_id', $invoice->id)
                ->orderByDesc('sequence')
                ->firstOrFail()
                ->status,
        );
    }

    public function test_a_payment_smaller_than_the_first_slice_leaves_it_pending(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('invoices.payments.store', $invoice), [
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(
            InstallmentStatus::Pending,
            $invoice->installments()->firstOrFail()->status,
        );
        $this->assertSame('8000.00', $invoice->refresh()->balance_amount);
    }

    public function test_resaving_a_plan_keeps_already_settled_slices(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $first = $invoice->installments()->firstOrFail();
        $this->actingAs($user)->post(route('invoices.installments.collect', [$invoice, $first]));

        // The balance is now 6000, so the plan must be rewritten for that.
        $this->schedule($invoice, [
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => '3000.00'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(InstallmentStatus::Paid, $first->refresh()->status);
        $this->assertSame(2, $invoice->installments()->where('status', 'pending')->count());
    }

    public function test_a_pending_slice_can_be_removed_but_a_paid_one_cannot(): void
    {
        $invoice = $this->invoice(9000);
        $user = $invoice->tenant->users()->first();

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $pending = Installment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->orderBy('sequence')
            ->firstOrFail();
        $second = Installment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->orderByDesc('sequence')
            ->firstOrFail();

        $this->actingAs($user)->post(route('invoices.installments.collect', [$invoice, $pending]));

        $this->actingAs($user)
            ->delete(route('invoices.installments.destroy', [$invoice, $second]))
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('invoices.installments.destroy', [$invoice, $pending]))
            ->assertStatus(422);
    }

    public function test_the_payment_plan_shows_on_the_invoice_page(): void
    {
        $invoice = $this->invoice(9000);

        $this->schedule($invoice, [
            ['due_date' => now()->toDateString(), 'amount' => '3000.00'],
            ['due_date' => now()->addMonth()->toDateString(), 'amount' => '6000.00'],
        ])->assertRedirect();

        $this->actingAs($invoice->tenant->users()->first())
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('invoices/show')
                ->has('invoice.installments', 2)
                ->where('installmentPlan.planned_total', 9000)
                ->where('installmentPlan.collected_total', 0)
            );
    }

    public function test_a_quotation_cannot_carry_a_payment_plan(): void
    {
        $user = $this->adminFor();
        $customer = $this->customerFor($user);

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
                'item_name' => 'Quoted work',
                'quantity' => 1,
                'rate_type' => 'fixed',
                'rate' => 5000,
                'tax_rate' => 0,
                'discount' => 0,
                'charges' => [],
            ]],
        ])->assertRedirect();

        $quotation = Invoice::where('document_type', DocumentType::Quotation->value)->firstOrFail();

        $this->actingAs($user)->post(route('invoices.installments.store', $quotation), [
            'installments' => [[
                'due_date' => now()->toDateString(),
                'amount' => 5000,
                'notes' => null,
            ]],
        ])->assertStatus(422);
    }

    protected function tenant(): Tenant
    {
        return Tenant::factory()->create();
    }
}
