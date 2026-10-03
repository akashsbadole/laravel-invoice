<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The quotation lifecycle: draft → sent → accepted/rejected → converted.
 *
 * Kept in one place so staff changes and customer decisions made from the
 * shared link follow exactly the same rules.
 */
class QuotationService
{
    public function currentStatus(Invoice $quotation): QuotationStatus
    {
        // An expiry date outranks the stored status: a quotation whose window
        // has closed reads as expired even though nobody has touched it yet.
        if ($this->hasLapsed($quotation)) {
            return QuotationStatus::Expired;
        }

        if ($quotation->quotation_status !== null) {
            return $quotation->quotation_status;
        }

        // Fall back to the invoice status for quotations created before the
        // lifecycle existed.
        return $quotation->status === InvoiceStatus::Sent
            ? QuotationStatus::Sent
            : QuotationStatus::Draft;
    }

    /**
     * Whether a quotation's validity window has closed.
     *
     * Only an undecided quotation can lapse. An accepted or converted quote is
     * a commitment, and a rejected one is already closed, so none of those
     * should be overwritten by a nightly sweep.
     */
    public function hasLapsed(Invoice $quotation): bool
    {
        if ($quotation->quotation_valid_until === null) {
            return false;
        }

        $status = $this->storedStatus($quotation);

        if ($status->isDecided()) {
            return false;
        }

        return $quotation->quotation_valid_until->isPast();
    }

    /**
     * Mark every lapsed quotation expired.
     *
     * Run daily from the scheduler. Returns how many changed so the command
     * can report something useful and so tests can assert the effect.
     */
    public function expireLapsed(?Carbon $asOf = null): int
    {
        $asOf ??= now();

        $expired = 0;

        Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->whereNotNull('quotation_valid_until')
            ->where('quotation_valid_until', '<', $asOf)
            // Skip anything already decided so a nightly sweep cannot undo a
            // customer decision that landed after the quote lapsed.
            ->where(function (Builder $q): void {
                $q->whereNull('quotation_status')
                    ->orWhereIn('quotation_status', [
                        QuotationStatus::Draft->value,
                        QuotationStatus::Sent->value,
                    ]);
            })
            ->each(function (Invoice $quotation) use (&$expired): void {
                $this->transition($quotation, QuotationStatus::Expired, note: 'Validity window closed.');
                $expired++;
            });

        return $expired;
    }

    /**
     * The status actually persisted on the row.
     *
     * Deliberately separate from currentStatus(): the expiry overlay is a
     * read-time interpretation, while transitions must reason about what is
     * stored. If the sweep compared against the overlay it would see "already
     * Expired" for the very row it is trying to expire and silently skip it.
     */
    protected function storedStatus(Invoice $quotation): QuotationStatus
    {
        return $quotation->quotation_status ?? QuotationStatus::Draft;
    }

    /**
     * Staff-driven transition.
     */
    public function transition(
        Invoice $quotation,
        QuotationStatus $to,
        ?User $by = null,
        ?string $note = null
    ): QuotationStatus {
        $this->guardIsQuotation($quotation);

        $from = $this->storedStatus($quotation);

        if ($from === $to) {
            return $from;
        }

        if ($from->isDecided() && $to !== QuotationStatus::Converted) {
            throw new RuntimeException(
                "This quotation is already {$from->label()} and cannot move to {$to->label()}."
            );
        }

        if ($quotation->converted_to_id !== null && $to !== QuotationStatus::Converted) {
            throw new RuntimeException('This quotation has already been converted to an invoice.');
        }

        if ($to === QuotationStatus::Converted && $quotation->converted_to_id === null) {
            throw new RuntimeException('Convert the quotation to an invoice first.');
        }

        $quotation->update([
            'quotation_status' => $to,
            // Keep the generic status in step so list filters stay meaningful.
            'status' => $this->invoiceStatusFor($to),
        ]);

        InvoiceEvent::log(
            $quotation,
            InvoiceEventType::Updated,
            [
                'action' => 'quotation_status',
                'from' => $from->value,
                'to' => $to->value,
                'note' => $note,
            ],
            $by?->id,
        );

        return $to;
    }

    /**
     * Customer decision made from the shared link.
     *
     * Uses the stored status rather than currentStatus(): a lapsed quotation
     * reads as Expired, and blocking a customer's answer because the validity
     * date passed would throw away a real decision. A quote can still be
     * accepted after expiry; staff then decide whether to honour it.
     */
    public function decide(Invoice $quotation, QuotationStatus $decision, ?string $response = null): QuotationStatus
    {
        if (! in_array($decision, [QuotationStatus::Accepted, QuotationStatus::Rejected], true)) {
            throw new RuntimeException('Invalid quotation decision.');
        }

        $from = $this->storedStatus($quotation);

        if ($from->isDecided()) {
            throw new RuntimeException("This quotation was already {$from->label()}.");
        }

        $quotation->update([
            'quotation_status' => $decision,
            'status' => $decision === QuotationStatus::Accepted
                ? InvoiceStatus::Accepted
                : InvoiceStatus::Draft,
            'quotation_response' => $response,
            'quotation_responded_at' => now(),
        ]);

        InvoiceEvent::log($quotation, InvoiceEventType::Updated, [
            'action' => 'quotation_decision',
            'decision' => $decision->value,
            'response' => $response,
        ]);

        return $decision;
    }

    /**
     * The updates a customer sees on the shared quotation.
     *
     * @return list<array{label:string,detail:string|null,at:string}>
     */
    public function updatesFor(Invoice $quotation): array
    {
        return $quotation->events()
            ->whereIn('event_type', [
                InvoiceEventType::Created->value,
                InvoiceEventType::Updated->value,
                InvoiceEventType::Sent->value,
            ])
            ->latest()
            ->get()
            ->map(fn (InvoiceEvent $event) => [
                'label' => $this->describe($event),
                'detail' => $event->meta['note'] ?? $event->meta['response'] ?? null,
                'at' => (string) $event->created_at,
            ])
            ->values()
            ->all();
    }

    protected function describe(InvoiceEvent $event): string
    {
        // Read `meta`: that is the column InvoiceEvent::log() writes. An earlier
        // version read `$event->properties`, an attribute that does not exist,
        // so every activity row silently lost its detail.
        $properties = $event->meta ?? [];
        $action = $properties['action'] ?? null;

        return match ($action) {
            'quotation_status' => 'Quotation marked as '
                .str_replace('_', ' ', (string) ($properties['to'] ?? '')),
            'quotation_decision' => ($properties['decision'] ?? '') === QuotationStatus::Accepted->value
                ? 'Customer accepted the quotation'
                : 'Customer declined the quotation',
            'link_generated' => 'Share link created',
            'changes_requested' => 'Customer asked for changes',
            // Deliberately just the headline: the reason already renders
            // as this event's detail from meta['note'].
            'revision' => 'Quotation revised to Rev '.($properties['revision'] ?? '?'),
            default => match ($event->event_type->value) {
                InvoiceEventType::Created->value => 'Quotation created',
                InvoiceEventType::Sent->value => 'Quotation sent',
                default => 'Quotation updated',
            },
        };
    }

    protected function invoiceStatusFor(QuotationStatus $status): InvoiceStatus
    {
        return match ($status) {
            QuotationStatus::Sent => InvoiceStatus::Sent,
            QuotationStatus::Accepted => InvoiceStatus::Accepted,
            QuotationStatus::Converted => InvoiceStatus::Converted,
            default => InvoiceStatus::Draft,
        };
    }

    protected function guardIsQuotation(Invoice $invoice): void
    {
        if (! $invoice->document_type->isQuotation()) {
            throw new RuntimeException('Only quotations have a lifecycle.');
        }
    }
}
