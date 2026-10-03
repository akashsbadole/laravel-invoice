<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Enums\QuotationStatus;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\QuotationFollowUpService;
use App\Services\QuotationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The quotation pipeline: what is open, what is it worth, and what needs chasing.
 *
 * Statuses are resolved through QuotationService::currentStatus() rather than
 * read from the column, because the expiry overlay is a read-time
 * interpretation — a GROUP BY would file a lapsed quote under its stored status
 * instead of Expired.
 */
class QuotationPipelineController extends Controller
{
    /** A viewed-but-unanswered quote older than this belongs on the chase list. */
    protected const STALE_CHASE_DAYS = 3;

    public function __construct(
        private readonly QuotationService $quotations,
        private readonly QuotationFollowUpService $followUps,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->canDo(Permission::ViewQuotations), 403);

        $quotations = Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->with([
                'customer:id,full_name,mobile_number',
                'shareLinks' => fn ($query) => $query->where('is_active', true),
            ])
            ->latest('invoice_date')
            ->get();

        $stages = collect(QuotationStatus::cases())
            ->map(fn (QuotationStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => 0,
                'total' => 0.0,
            ])
            ->keyBy('value')
            ->all();

        $rows = [];

        foreach ($quotations as $quotation) {
            $status = $this->quotations->currentStatus($quotation);
            $link = $quotation->shareLinks->sortByDesc('id')->first();

            $stages[$status->value]['count']++;
            $stages[$status->value]['total'] += (float) $quotation->grand_total;

            $rows[] = [
                'id' => $quotation->id,
                'invoice_number' => $quotation->invoice_number,
                'customer' => $quotation->customer?->full_name ?? '—',
                'customer_id' => $quotation->customer_id,
                'customer_phone' => $quotation->customer?->mobile_number,
                'grand_total' => (float) $quotation->grand_total,
                'status' => $status->value,
                'status_label' => $status->label(),
                'is_open' => $status->isOpen(),
                'valid_until' => $quotation->quotation_valid_until?->toDateString(),
                'viewed_at' => $link?->viewed_at?->toDateString(),
                'has_link' => $link !== null,
                'share_token' => $link?->token,
                'converted_to_id' => $quotation->converted_to_id,
                'can_convert' => $quotation->converted_to_id === null && in_array($status, [QuotationStatus::Draft, QuotationStatus::Sent, QuotationStatus::Accepted]),
                // Measured from the quotation's own date, matching the follow-up
                // rules — "how long has this been outstanding", not "since a row
                // was written".
                'age_days' => (int) (($quotation->invoice_date ?? $quotation->created_at)?->diffInDays(now()) ?? 0),
            ];
        }

        $settings = BusinessSetting::forTenant($request->user()->tenant_id);
        $openValues = [QuotationStatus::Draft->value, QuotationStatus::Sent->value];

        $totalQuotes = count($rows);
        $convertedCount = $stages[QuotationStatus::Converted->value]['count'] ?? 0;
        $acceptedCount = $stages[QuotationStatus::Accepted->value]['count'] ?? 0;
        $decidedCount = $totalQuotes - ($stages[QuotationStatus::Draft->value]['count'] ?? 0);
        $conversionRate = $decidedCount > 0 ? round((($convertedCount + $acceptedCount) / $decidedCount) * 100, 1) : 0.0;

        return Inertia::render('quotations/index', [
            'stages' => array_values($stages),
            'quotations' => $rows,
            'openValue' => (float) collect($stages)
                ->filter(fn (array $stage): bool => in_array($stage['value'], $openValues, true))
                ->sum('total'),
            'needsChase' => $this->needsChase($rows),
            'expiringSoon' => $this->expiringSoon($rows),
            'followUpEnabled' => (bool) $settings->quotation_followup_enabled,
            'followUpDays' => (int) ($settings->quotation_followup_days ?? 2),
            'dueCount' => $quotations
                ->filter(fn (Invoice $quotation): bool => $this->followUps->isDue($quotation))
                ->count(),
            'analytics' => [
                'total_quotations' => $totalQuotes,
                'accepted_count' => $acceptedCount,
                'converted_count' => $convertedCount,
                'conversion_rate' => $conversionRate,
            ],
            'acceptedQuotations' => array_values(array_filter($rows, fn ($r) => $r['status'] === QuotationStatus::Accepted->value && ! $r['converted_to_id'])),
        ]);
    }

    /**
     * Opened by the customer but still unanswered — the shop knows they looked,
     * and the quote is going cold.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function needsChase(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['status'] === QuotationStatus::Sent->value
                && $row['viewed_at'] !== null
                && $row['age_days'] >= self::STALE_CHASE_DAYS,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function expiringSoon(array $rows): array
    {
        $cutoff = now()->addDays(QuotationFollowUpService::EXPIRING_WINDOW_DAYS);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['is_open']
                && $row['has_link']
                && $row['valid_until'] !== null
                && $row['valid_until'] <= $cutoff->toDateString(),
        ));
    }
}
