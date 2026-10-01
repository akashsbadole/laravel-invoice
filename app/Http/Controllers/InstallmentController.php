<?php

namespace App\Http\Controllers;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoices\StoreInstallmentScheduleRequest;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Payment plans: an invoice balance split into dated slices the customer has
 * agreed to, each of which can be collected independently.
 */
class InstallmentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(StoreInstallmentScheduleRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_if($invoice->document_type->isQuotation(), 422, 'Quotations cannot carry a payment plan.');
        abort_if($invoice->status === InvoiceStatus::Cancelled, 422, 'A cancelled invoice cannot carry a payment plan.');

        $schedule = $request->validated('installments');

        DB::transaction(function () use ($invoice, $schedule, $request) {
            // Settled slices carry a payment record, so they must not be
            // silently deleted by re-saving the plan.
            $invoice->installments()->where('status', 'pending')->delete();

            // Sequence numbers must keep climbing past the retained,
            // already-settled slices — the (invoice_id, sequence) pair is
            // unique.
            $sequence = (int) $invoice->installments()->max('sequence');

            foreach ($schedule as $slice) {
                $invoice->installments()->create([
                    'sequence' => ++$sequence,
                    'due_date' => $slice['due_date'],
                    'amount' => $slice['amount'],
                    'status' => InstallmentStatus::Pending,
                    'notes' => $slice['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }

            InvoiceEvent::log(
                $invoice,
                InvoiceEventType::Updated,
                ['action' => 'installment_schedule_saved', 'count' => count($schedule)],
                $request->user()->id,
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment plan saved.')]);

        return back();
    }

    public function collect(Request $request, Invoice $invoice, Installment $installment): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);
        abort_unless($installment->invoice_id === $invoice->id, 404);
        abort_if($installment->status->isSettled(), 422, 'This installment is already settled.');

        $payment = $this->payments->record($invoice, [
            'amount' => $installment->amount,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'Installment #'.$installment->sequence,
        ], $request->user());

        $installment->update([
            'status' => InstallmentStatus::Paid,
            'payment_id' => $payment->id,
            'paid_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Installment collected.')]);

        return back();
    }

    public function destroy(Invoice $invoice, Installment $installment): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($installment->invoice_id === $invoice->id, 404);
        abort_if($installment->status === InstallmentStatus::Paid, 422, 'Settled installments cannot be removed.');

        $installment->delete();

        return back();
    }
}
