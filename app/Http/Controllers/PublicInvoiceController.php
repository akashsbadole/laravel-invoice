<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\QuotationActivity;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\InvoiceEvent;
use App\Models\InvoiceShareLink;
use App\Models\InvoiceTemplate;
use App\Services\CustomerAdvanceController;
use App\Services\InvoiceCloner;
use App\Services\QuotationNotifier;
use App\Services\QuotationService;
use App\Support\Industry;
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
        $shareLink = InvoiceShareLink::query()->where('token', $token)->first();

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

        $invoice = $shareLink->invoice()->with([
            'customer', 'salesperson', 'items.charges', 'payments', 'template',
        ])->firstOrFail();

        $template = $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id);
        $settings = BusinessSetting::forTenant($invoice->tenant_id);
        $isQuotation = $invoice->document_type->isQuotation();

        // The customer just proved they opened it — the shop's cue to follow up
        // while interest is fresh.
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
            'invoice' => $invoice,
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
    }

    public function verifyPassword(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->where('token', $token)->firstOrFail();

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
        $shareLink = InvoiceShareLink::query()->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            abort(403);
        }

        $settings = BusinessSetting::forTenant($shareLink->invoice->tenant_id);
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
            $this->quotations->decide($invoice, $decision, $response);

            if ($decision === QuotationStatus::Accepted && $settings->quotation_auto_convert) {
                $industry = $invoice->tenant->industry ?? Industry::default();
                $documentType = Industry::usesWeightFields($industry)
                    ? DocumentType::JewelryInvoice
                    : DocumentType::GeneralInvoice;

                $newInvoice = app(InvoiceCloner::class)->cloneAsNew(
                    $invoice,
                    null,
                    $documentType->value,
                    now()->addDays(7)->toDateString(),
                );

                app(CustomerAdvanceController::class)->applyAvailableAdvancesToInvoice($newInvoice, null);

                $this->quotations->transition($invoice, QuotationStatus::Converted, null);
                $invoice->update(['converted_to_id' => $newInvoice->id]);
            }
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
    }

    /**
     * A customer asking for changes is still negotiating, not rejecting.
     * Record what they asked for and keep the quotation open, so the shop
     * never loses the thread to a one-word "reject".
     */
    public function requestChanges(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->where('token', $token)->firstOrFail();
        abort_unless($shareLink->isUsable(), 404);

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            abort(403);
        }

        $settings = BusinessSetting::forTenant($shareLink->invoice->tenant_id);
        abort_unless($settings->quotation_customer_decisions, 404);

        $invoice = $shareLink->invoice()->with('events')->firstOrFail();
        abort_unless($invoice->document_type->isQuotation(), 404);

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
    }

    /**
     * @param  array<string,mixed>  $validated
     */
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
