<?php

namespace App\Http\Controllers;

use App\Concerns\TenantScope;
use App\Enums\InvoiceEventType;
use App\Enums\QuotationActivity;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use App\Models\InvoiceTemplate;
use App\Models\Tenant;
use App\Services\QuotationNotifier;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PublicInvoiceController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotations,
        private readonly QuotationNotifier $notifier,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $shareLink = InvoiceShareLink::query()->withoutGlobalScope(TenantScope::class)->where('token', $token)->first();

        if (! $shareLink || ! $shareLink->isUsable()) {
            return Inertia::render('invoices/public', [
                'status' => 'unavailable',
            ]);
        }

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            return Inertia::render('invoices/public', [
                'status' => 'password_required',
                'token' => $token,
            ]);
        }

        // Captured before markViewed() writes the timestamp: only the first look
        // is news, and a customer refreshing the page must not re-alert staff.
        $firstView = $shareLink->viewed_at === null;

        $shareLink->markViewed();

        return Tenant::runInContext($shareLink->tenant_id, function () use ($shareLink, $firstView, $token) {
            $invoice = $shareLink->invoice()->with([
                'customer', 'salesperson', 'items.charges', 'payments', 'template',
            ])->firstOrFail();

            $template = $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id);
            $settings = BusinessSetting::forTenant($invoice->tenant_id);
            $isQuotation = $invoice->document_type->isQuotation();

            if ($firstView) {
                InvoiceEvent::log($invoice, InvoiceEventType::LinkViewed, [
                    'action' => 'link_viewed',
                    'token' => $token,
                ]);
                if ($isQuotation) {
                    $this->notifier->activity($invoice, QuotationActivity::Viewed);
                }
            }

            return Inertia::render('invoices/public', [
                'status' => 'ok',
                'token' => $token,
                'invoice' => $this->sanitizePublicInvoice($invoice),
                'template' => $template->layout_config + InvoiceTemplate::defaultLayoutConfig(),
                'business' => [
                    ...$settings->only([
                        'business_name', 'logo_path', 'address', 'phone', 'email',
                        'website', 'tax_number', 'footer_text',
                    ]),
                    'upi_id' => $settings->bank_details['upi_id'] ?? null,
                ],
                'quotation' => $isQuotation ? [
                    'status' => $this->quotations->currentStatus($invoice)->value,
                    'is_open' => $this->quotations->currentStatus($invoice)->isOpen(),
                    'can_decide' => $settings->quotation_customer_decisions
                        && $invoice->document_type->isQuotation()
                        && $invoice->converted_to_id === null,
                    'response' => $invoice->quotation_response,
                    'updates' => $settings->quotation_show_updates
                        ? $this->quotations->updatesFor($invoice)
                        : [],
                ] : null,
            ]);
        });
    }

    public function verifyPassword(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->withoutGlobalScope(TenantScope::class)->where('token', $token)->firstOrFail();

        $request->validate(['password' => ['required', 'string']]);

        if (! $shareLink->isUsable() || ! $shareLink->checkPassword($request->string('password'))) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put("invoice_share_verified.{$token}", true);

        return to_route('invoices.public.show', $token);
    }

    /**
     * Customer accepts or declines a quotation from the shared link.
     * Gated by a tenant setting, since some shops decide by phone instead.
     */
    public function decide(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->withoutGlobalScope(TenantScope::class)->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            abort(403);
        }

        $result = Tenant::runInContext($shareLink->tenant_id, function () use ($request, $shareLink) {
            $settings = BusinessSetting::forTenant($shareLink->tenant_id);
            abort_unless($settings->quotation_customer_decisions, 404);

            $invoice = $shareLink->invoice()->with('events')->firstOrFail();
            abort_unless($invoice->document_type->isQuotation(), 404);

            $validated = $request->validate([
                'decision' => ['required', Rule::in([QuotationStatus::Accepted->value, QuotationStatus::Rejected->value])],
                'response' => ['nullable', 'string', 'max:1000'],
                'name' => ['nullable', 'string', 'max:120'],
            ]);

            $decision = QuotationStatus::from($validated['decision']);
            $response = $this->responseText($validated, $decision);

            try {
                // Recording an acceptance deliberately does NOT create an invoice.
                // A customer tapping "Accept" on a link is not a sale: nobody has
                // re-confirmed the metal rate and nobody has taken payment, so the
                // money must wait for a human at the counter to convert it.
                $this->quotations->decide($invoice, $decision, $response);
            } catch (RuntimeException $e) {
                return back()->withErrors(['decision' => $e->getMessage()]);
            }

            // The answer is the whole point of sharing a quotation — tell the shop
            // rather than leaving it to be discovered on the next page refresh.
            $this->notifier->activity(
                $invoice,
                $decision === QuotationStatus::Accepted
                    ? QuotationActivity::Accepted
                    : QuotationActivity::Declined,
                $response,
            );

            return back()->with(
                'message',
                $decision === QuotationStatus::Accepted
                    ? 'Thank you — your acceptance has been recorded.'
                    : 'Thank you — we have noted your response.',
            );
        });

        return $result;
    }

    /**
     * A customer asking for changes is still negotiating, not rejecting.
     * Record what they asked for and keep the quotation open, so the shop
     * never loses the thread to a one-word "reject".
     */
    public function requestChanges(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->withoutGlobalScope(TenantScope::class)->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            abort(403);
        }

        $result = Tenant::runInContext($shareLink->tenant_id, function () use ($request, $shareLink) {
            $settings = BusinessSetting::forTenant($shareLink->tenant_id);
            abort_unless($settings->quotation_customer_decisions, 404);

            $invoice = $shareLink->invoice()->with('events')->firstOrFail();
            abort_unless($invoice->document_type->isQuotation(), 404);
            abort_if($invoice->converted_to_id !== null, 404);
            abort_if($invoice->quotation_status?->isDecided(), 404);

            $validated = $request->validate([
                'response' => ['required', 'string', 'max:1000'],
                'name' => ['nullable', 'string', 'max:120'],
            ]);

            $text = trim($validated['response']);
            $name = trim((string) ($validated['name'] ?? ''));

            if ($name !== '') {
                $text = "{$text} — {$name}";
            }

            $invoice->update([
                'quotation_response' => $text,
                'quotation_responded_at' => now(),
            ]);

            InvoiceEvent::log($invoice, InvoiceEventType::Updated, [
                'action' => 'changes_requested',
                'response' => $text,
            ]);

            $this->notifier->activity($invoice, QuotationActivity::ChangesRequested, $text);

            return back()->with(
                'message',
                'Thanks — we have recorded your request and will get back to you.',
            );
        });

        return $result;
    }

    /**
     * @param  array<string,mixed>  $validated
     */
    protected function sanitizePublicInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'rate_locked_at' => $invoice->rate_locked_at?->toDateString(),
            'revision_number' => $invoice->revision_number,
            'document_type' => $invoice->document_type?->value,
            'status' => $invoice->status?->value,
            'subtotal' => (float) $invoice->subtotal,
            'charges_summary' => $invoice->charges_summary,
            'discount' => (float) $invoice->discount,
            'tax' => (float) $invoice->tax,
            'tax_breakdown' => $invoice->tax_breakdown,
            'tcs_rate' => (float) $invoice->tcs_rate,
            'tcs_amount' => (float) $invoice->tcs_amount,
            'round_off' => (float) $invoice->round_off,
            'grand_total' => (float) $invoice->grand_total,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance_amount' => (float) $invoice->balance_amount,
            'terms' => $invoice->terms,
            'converted_to_id' => $invoice->converted_to_id,
            'quotation_response' => $invoice->quotation_response,
            'customer' => $invoice->customer ? $invoice->customer->only([
                'full_name', 'mobile_number', 'email', 'address', 'tax_number',
            ]) : null,
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_label' => $item->unit_label,
                'rate_type' => $item->rate_type?->value,
                'rate' => (float) $item->rate,
                'net_weight' => $item->net_weight !== null ? (float) $item->net_weight : null,
                'gross_weight' => $item->gross_weight !== null ? (float) $item->gross_weight : null,
                'wastage_percent' => $item->wastage_percent !== null ? (float) $item->wastage_percent : null,
                'making_charge' => $item->making_charge !== null ? (float) $item->making_charge : null,
                'item_total' => (float) $item->item_total,
                'charges' => $item->charges->map(fn ($c) => [
                    'id' => $c->id,
                    'charge_name' => $c->charge_name,
                    'amount' => (float) $c->amount,
                ])->values()->all(),
            ])->values()->all(),
            'payments' => $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->toDateString(),
                'amount' => (float) $p->amount,
                'payment_method' => $p->payment_method?->value,
                'reference_number' => $p->reference_number,
            ])->values()->all(),
            'template' => $invoice->template ? [
                'id' => $invoice->template->id,
                'name' => $invoice->template->name,
                'layout_config' => $invoice->template->layout_config,
            ] : null,
        ];
    }

    protected function responseText(array $validated, QuotationStatus $decision): ?string
    {
        $text = trim((string) ($validated['response'] ?? ''));
        $name = trim((string) ($validated['name'] ?? ''));

        if ($name !== '') {
            $text = $text === '' ? "Accepted by {$name}" : "{$text} — {$name}";
        }

        return $text === '' ? null : $text;
    }
}
