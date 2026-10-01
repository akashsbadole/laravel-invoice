<?php

namespace App\Services;

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
}
