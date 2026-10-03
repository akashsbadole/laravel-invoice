<?php

namespace App\Http\Controllers;

use App\Enums\AdvanceStatus;
use App\Enums\Permission;
use App\Http\Requests\Customers\StoreCustomerAdvanceRequest;
use App\Http\Requests\Invoices\ApplyCustomerAdvanceRequest;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CustomerAdvanceController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Money taken before (or without) an invoice: a booking fee, a
     * reservation, a part-payment promised against future work.
     */
    public function store(StoreCustomerAdvanceRequest $request, Customer $customer): RedirectResponse
    {
        $advance = $customer->advances()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Advance of :amount recorded.', [
                'amount' => number_format((float) $advance->amount, 2),
            ]),
        ]);

        return back();
    }

    /**
     * Programmatically apply all available advances for the invoice's customer
     * up to the balance amount.
     */
    public function applyAvailableAdvancesToInvoice(Invoice $invoice, ?\App\Models\User $user = null): float
    {
        $advances = CustomerAdvance::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('status', AdvanceStatus::Available->value)
            ->whereRaw('amount > applied_amount')
            ->orderBy('advance_date')
            ->get();

        $totalApplied = 0.0;

        foreach ($advances as $advance) {
            $invoice->refresh();
            $balance = (float) $invoice->balance_amount;

            if ($balance <= 0.005) {
                break;
            }

            $available = $advance->availableAmount();
            if ($available <= 0.005) {
                continue;
            }

            $amountToApply = round(min($available, $balance), 2);

            DB::transaction(function () use ($invoice, $advance, $amountToApply, $user) {
                $payment = $this->payments->record($invoice, [
                    'amount' => $amountToApply,
                    'payment_date' => $advance->advance_date->toDateString(),
                    'payment_method' => $advance->payment_method->value,
                    'reference_number' => $advance->reference_number ?? 'ADV-'.$advance->id,
                    'notes' => $advance->notes
                        ? 'Applied advance: '.$advance->notes
                        : 'Applied advance on quotation conversion',
                ], $user);

                $this->payments->settleMatchingInstallments($invoice, $payment);

                $advance->applied_amount = round((float) $advance->applied_amount + $amountToApply, 2);
                $advance->status = $advance->availableAmount() > 0.005
                    ? AdvanceStatus::Available
                    : AdvanceStatus::Applied;
                $advance->save();
            });

            $totalApplied += $amountToApply;
        }

        return $totalApplied;
    }

    /**
     * Consume an advance against one of the customer's invoices. The money
     * becomes a real payment (so collected totals and the payment page stay
     * honest) while the advance keeps a ledger of what is still available.
     */
    public function apply(ApplyCustomerAdvanceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);

        $advance = CustomerAdvance::find($request->validated('advance_id'));

        abort_unless($advance !== null, 422, 'Advance not found.');
        abort_unless($advance->customer_id === $invoice->customer_id, 422,
            'That advance belongs to a different customer.');
        abort_unless($advance->isAvailable(), 422,
            'That advance has nothing available to apply.');

        $max = round(min($advance->availableAmount(), (float) $invoice->balance_amount), 2);

        if ($max <= 0) {
            return back()->withErrors(['amount' => 'This invoice has nothing outstanding.']);
        }

        $amount = round((float) ($request->validated('amount') ?? $max), 2);

        if ($amount > $max) {
            return back()->withErrors([
                'amount' => "At most {$max} can be applied here (advance available: "
                    .number_format($advance->availableAmount(), 2).').',
            ]);
        }

        DB::transaction(function () use ($invoice, $advance, $amount, $request) {
            // Dated to when the money was actually taken, not when it is
            // being consumed — monthly collected totals must not shift.
            $payment = $this->payments->record($invoice, [
                'amount' => $amount,
                'payment_date' => $advance->advance_date->toDateString(),
                'payment_method' => $advance->payment_method->value,
                'reference_number' => $advance->reference_number ?? 'ADV-'.$advance->id,
                'notes' => $advance->notes
                    ? 'Applied advance: '.$advance->notes
                    : 'Applied advance',
            ], $request->user());

            $this->payments->settleMatchingInstallments($invoice, $payment);

            $advance->applied_amount = round((float) $advance->applied_amount + $amount, 2);
            $advance->status = $advance->availableAmount() > 0.005
                ? AdvanceStatus::Available
                : AdvanceStatus::Applied;
            $advance->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance applied.')]);

        return back();
    }

    /**
     * Give an unused advance back. Money already applied to invoices is
     * settled there — it does not un-happen.
     */
    public function refund(Request $request, Customer $customer, CustomerAdvance $advance): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::RecordPayments), 403);
        abort_unless($advance->customer_id === $customer->id, 404);
        abort_if((float) $advance->applied_amount > 0, 422,
            'This advance was already applied to an invoice and cannot be refunded.');

        $advance->update(['status' => AdvanceStatus::Refunded]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Advance refunded.')]);

        return back();
    }
}
