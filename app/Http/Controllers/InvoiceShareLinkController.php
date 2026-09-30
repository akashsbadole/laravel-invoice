<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceEventType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InvoiceShareLinkController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('share', $invoice);

        $validated = $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        $shareLink = new InvoiceShareLink([
            'invoice_id' => $invoice->id,
            'expires_at' => isset($validated['expires_in_days'])
                ? now()->addDays($validated['expires_in_days'])
                : null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $shareLink->token = InvoiceShareLink::generateToken();
        $shareLink->setPassword($validated['password'] ?? null);
        $shareLink->save();

        InvoiceEvent::log($invoice, InvoiceEventType::Sent, ['action' => 'link_generated'], $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Share link created.')]);

        return back();
    }

    public function deactivate(Request $request, Invoice $invoice, InvoiceShareLink $shareLink): RedirectResponse
    {
        Gate::authorize('share', $invoice);
        abort_unless($shareLink->invoice_id === $invoice->id, 404);

        $shareLink->update(['is_active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Share link disabled.')]);

        return back();
    }

    public function markSent(Request $request, Invoice $invoice, InvoiceShareLink $shareLink): RedirectResponse
    {
        Gate::authorize('share', $invoice);
        abort_unless($shareLink->invoice_id === $invoice->id, 404);

        $validated = $request->validate([
            'via' => ['required', Rule::in(['email', 'whatsapp', 'sms', 'copy'])],
        ]);

        $shareLink->update(['sent_via' => $validated['via'], 'sent_at' => now()]);

        InvoiceEvent::log($invoice, InvoiceEventType::Sent, ['via' => $validated['via']], $request->user()->id);

        return back();
    }

    public function sendSms(Request $request, Invoice $invoice, InvoiceShareLink $shareLink, SmsService $sms): RedirectResponse
    {
        Gate::authorize('share', $invoice);
        abort_unless($shareLink->invoice_id === $invoice->id && $shareLink->isUsable(), 404);

        $invoice->loadMissing('customer');
        $business = BusinessSetting::current();
        $url = route('invoices.public.show', $shareLink->token);

        $message = "Invoice {$invoice->invoice_number} from {$business->business_name}: Rs."
            .number_format((float) $invoice->grand_total, 2).". View/download: {$url}";

        $log = $sms->send($invoice->customer->mobile_number, $message, [
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'created_by' => $request->user()->id,
        ]);

        if ($log->status !== 'failed') {
            $shareLink->update(['sent_via' => 'sms', 'sent_at' => now()]);
            InvoiceEvent::log($invoice, InvoiceEventType::Sent, ['via' => 'sms'], $request->user()->id);
        }

        Inertia::flash('toast', SmsService::toast($log));

        return back();
    }
}
