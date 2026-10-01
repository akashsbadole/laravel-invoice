<?php

namespace App\Http\Controllers;

use App\Enums\InstallmentStatus;
use App\Http\Requests\Invoices\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentReminderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'method', 'from', 'to']);

        $query = Payment::query()
            ->with(['invoice.customer:id,full_name', 'receiver:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($qq) => $qq
                ->where('reference_number', 'like', "%{$s}%")
                ->orWhereHas('invoice', fn ($iq) => $iq->where('invoice_number', 'like', "%{$s}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('full_name', 'like', "%{$s}%")))))
            ->when(($filters['method'] ?? 'all') !== 'all', fn ($q) => $q->where('payment_method', $filters['method']))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('payment_date', '<=', $v));

        return Inertia::render('payments/index', [
            'total' => (float) (clone $query)->sum('amount'),
            'payments' => $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function store(StorePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);

        $payment = $this->payments->record($invoice, $request->validated(), $request->user());

        // Collecting against an agreed plan settles the oldest unpaid slice.
        $this->settleMatchingInstallments($invoice, $payment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }

    public function remind(Request $request, Invoice $invoice, PaymentReminderService $reminders): RedirectResponse
    {
        Gate::authorize('recordPayment', $invoice);

        if ((float) $invoice->balance_amount <= 0) {
            return back()->withErrors(['reminder' => 'This invoice has nothing outstanding.']);
        }

        // Staff-initiated, so it bypasses the nightly 3-day throttle.
        $sent = $reminders->sendForInvoice($invoice, $request->user()->id, force: true);

        if ($sent === []) {
            return back()->withErrors(['reminder' => 'No contact details on file for this customer.']);
        }

        $channels = collect($sent)
            ->map(fn (string $status, string $channel) => $channel.' ('.$status.')')
            ->implode(', ');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Reminder sent: {$channels}",
        ]);

        return back();
    }

    /**
     * Mark any pending installment the payment fully covers, oldest first, so
     * the plan and the payment ledger never disagree.
     */
    protected function settleMatchingInstallments(Invoice $invoice, Payment $payment): void
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
