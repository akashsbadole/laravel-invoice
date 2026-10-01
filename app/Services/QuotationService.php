<?php

namespace App\Services;

use App\Enums\InvoiceEventType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;
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
     * Staff-driven transition.
     */
    public function transition(
        Invoice $quotation,
        QuotationStatus $to,
        ?User $by = null,
        ?string $note = null
    ): QuotationStatus {
        $this->guardIsQuotation($quotation);

        $from = $this->currentStatus($quotation);

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
     */
    public function decide(Invoice $quotation, QuotationStatus $decision, ?string $response = null): QuotationStatus
    {
        if (! in_array($decision, [QuotationStatus::Accepted, QuotationStatus::Rejected], true)) {
            throw new RuntimeException('Invalid quotation decision.');
        }

        $from = $this->currentStatus($quotation);

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
                'detail' => $event->properties['note'] ?? $event->properties['response'] ?? null,
                'at' => (string) $event->created_at,
            ])
            ->values()
            ->all();
    }

    protected function describe(InvoiceEvent $event): string
    {
        $properties = $event->properties ?? [];
        $action = $properties['action'] ?? null;

        return match ($action) {
            'quotation_status' => 'Quotation marked as '
                .str_replace('_', ' ', (string) ($properties['to'] ?? '')),
            'quotation_decision' => ($properties['decision'] ?? '') === QuotationStatus::Accepted->value
                ? 'Customer accepted the quotation'
                : 'Customer declined the quotation',
            'link_generated' => 'Share link created',
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
