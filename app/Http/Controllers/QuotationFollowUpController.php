<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Services\QuotationFollowUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The manual "chase this quote now" action behind the pipeline board.
 *
 * The nightly command does this on its own schedule; this is for the shopkeeper
 * who is looking at a specific quotation and does not want to wait.
 */
class QuotationFollowUpController extends Controller
{
    public function __construct(private readonly QuotationFollowUpService $followUps) {}

    public function store(Request $request, Invoice $quotation): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::SendMessages), 403);
        abort_unless($quotation->document_type->isQuotation(), 404);

        $sent = $this->followUps->sendForQuotation($quotation, $request->user()->id, force: true);

        if ($sent === []) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Nothing to send — the quotation is already answered, or the customer has no contact detail to reach.'),
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Follow-up sent via :channels.', ['channels' => implode(', ', array_keys($sent))]),
            ]);
        }

        return back();
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::SendMessages), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:invoices,id'],
        ]);

        $quotations = Invoice::query()
            ->whereIn('id', $validated['ids'])
            ->where('document_type', DocumentType::Quotation->value)
            ->get();

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($quotations as $quotation) {
            $sent = $this->followUps->sendForQuotation($quotation, $request->user()->id, force: true);

            if ($sent !== []) {
                $sentCount++;
            } else {
                $skippedCount++;
            }
        }

        $message = match (true) {
            $sentCount > 0 && $skippedCount > 0 => "Follow-up sent to {$sentCount} quotation(s). {$skippedCount} skipped.",
            $sentCount > 0 => "Follow-up sent to {$sentCount} quotation(s).",
            default => 'No follow-ups were sent. Check that the quotations are open and have customer contact details.',
        };

        Inertia::flash('toast', [
            'type' => $sentCount > 0 ? 'success' : 'info',
            'message' => __($message),
        ]);

        return back();
    }
}
