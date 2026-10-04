<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function createUnpaidInvoiceFor($user, float $amount = 1000): Invoice
    {
        $customer = $this->customerFor($user);

        return $this->inTenant($user, fn () => Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('#####'),
            'document_type' => DocumentType::GeneralInvoice->value,
            'invoice_date' => now()->toDateString(),
            'status' => 'unpaid',
            'subtotal' => $amount,
            'grand_total' => $amount,
            'paid_amount' => 0,
            'balance_amount' => $amount,
            'pricing_mode' => 'manual',
            'tax_mode' => 'single',
            'created_by' => $user->id,
        ]));
    }

    public function test_overpayment_is_rejected(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createUnpaidInvoiceFor($user, 1000);

        $this->actingAs($user)
            ->postJson(route('invoices.payments.store', $invoice), [
                'amount' => 1500,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_payment_recording_and_reversal(): void
    {
        $user = $this->adminFor();
        $invoice = $this->createUnpaidInvoiceFor($user, 1000);

        $this->actingAs($user)
            ->post(route('invoices.payments.store', $invoice), [
                'amount' => 400,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(400, $invoice->paid_amount);
        $this->assertEquals(600, $invoice->balance_amount);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('payments.reverse', $payment), [
                'reason' => 'Customer bounced cheque',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(1000, $invoice->balance_amount);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }
}
