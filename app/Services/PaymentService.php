<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceEventType;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single entry point for recording money against an invoice, so a manual
 * payment, an advance/booking payment and a collected installment all keep
 * paid_amount, balance_amount and status in step.
 */
class PaymentService
{
    /**
     * @param  array{amount:string|float, payment_method:string, payment_date:string, reference_number?:string|null, notes?:string|null}  $attributes
     */
    public function record(Invoice $invoice, array $attributes, ?User $receivedBy = null): Payment
    {
        return DB::transaction(function () use ($invoice, $attributes, $receivedBy) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $lockedInvoice->load('payments');
            $lockedInvoice->recalculatePaymentStatus();

            $amount = (float) $attributes['amount'];
            $balance = (float) $lockedInvoice->balance_amount;

            if ($amount > $balance + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Payment amount exceeds remaining balance of Rs. %s.', number_format($balance, 2)),
                ]);
            }

            $payment = $lockedInvoice->payments()->create([
                ...$attributes,
                'received_by' => $receivedBy?->id,
            ]);

            $lockedInvoice->load('payments');
            $lockedInvoice->recalculatePaymentStatus();
            $lockedInvoice->save();

            InvoiceEvent::log(
                $lockedInvoice,
                InvoiceEventType::PaymentRecorded,
                ['amount' => $payment->amount, 'payment_id' => $payment->id],
                $receivedBy?->id,
            );

            return $payment;
        });
    }

    public function reverse(Payment $payment, ?User $reversedBy = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($payment, $reversedBy, $reason) {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $lockedInvoice->installments()
                ->where('payment_id', $payment->id)
                ->update([
                    'status' => InstallmentStatus::Pending,
                    'payment_id' => null,
                    'paid_at' => null,
                ]);

            InvoiceEvent::log(
                $lockedInvoice,
                InvoiceEventType::Updated,
                [
                    'action' => 'payment_reversed',
                    'amount' => $payment->amount,
                    'payment_id' => $payment->id,
                    'reason' => $reason,
                ],
                $reversedBy?->id,
            );

            $payment->delete();

            $lockedInvoice->load('payments');
            $lockedInvoice->recalculatePaymentStatus();
            $lockedInvoice->save();
        });
    }

    /**
     * Collecting against an agreed plan settles the oldest unpaid slice, so
     * the plan and the payment ledger never disagree.
     */
    public function settleMatchingInstallments(Invoice $invoice, Payment $payment): void
    {
        $remaining = (float) $payment->amount;

        foreach ($invoice->installments()->where('status', 'pending')->get() as $installment) {
            if ($remaining + 0.005 < (float) $installment->amount) {
                break;
            }

            $installment->update([
                'status' => InstallmentStatus::Paid,
                'payment_id' => $payment->id,
                'paid_at' => now(),
            ]);

            $remaining -= (float) $installment->amount;
        }
    }
}
