<?php

namespace App\Services;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceEventType;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            $payment = $invoice->payments()->create([
                ...$attributes,
                'received_by' => $receivedBy?->id,
            ]);

            $invoice->load('payments');
            $invoice->recalculatePaymentStatus();
            $invoice->save();

            InvoiceEvent::log(
                $invoice,
                InvoiceEventType::PaymentRecorded,
                ['amount' => $payment->amount, 'payment_id' => $payment->id],
                $receivedBy?->id,
            );

            return $payment;
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
