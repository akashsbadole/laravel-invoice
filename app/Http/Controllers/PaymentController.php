<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceEventType;
use App\Http\Requests\Invoices\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
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

        DB::transaction(function () use ($request, $invoice) {
            $invoice->payments()->create([
                ...$request->validated(),
                'received_by' => $request->user()->id,
            ]);

            $invoice->load('payments');
            $invoice->recalculatePaymentStatus();
            $invoice->save();

            InvoiceEvent::log(
                $invoice,
                InvoiceEventType::PaymentRecorded,
                ['amount' => $request->validated('amount')],
                $request->user()->id,
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment recorded.')]);

        return back();
    }
}
