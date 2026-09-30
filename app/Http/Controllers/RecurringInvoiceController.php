<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RecurringProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RecurringInvoiceController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_if($invoice->document_type->isQuotation(), 422, 'Quotations cannot be recurring.');

        $validated = $request->validate([
            'frequency' => ['required', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'next_run_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        RecurringProfile::query()->updateOrCreate(
            ['source_invoice_id' => $invoice->id],
            [
                'customer_id' => $invoice->customer_id,
                'salesperson_id' => $invoice->salesperson_id,
                'frequency' => $validated['frequency'],
                'next_run_at' => $validated['next_run_at'],
                'is_active' => true,
                'created_by' => $request->user()->id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring schedule saved.')]);

        return back();
    }

    public function destroy(Request $request, Invoice $invoice, RecurringProfile $profile): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        abort_unless($profile->source_invoice_id === $invoice->id, 404);

        $profile->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring schedule removed.')]);

        return back();
    }
}
