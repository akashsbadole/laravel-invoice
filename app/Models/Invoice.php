<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\QuotationStatus;
use App\Enums\TaxMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'invoice_number', 'invoice_date', 'due_date', 'reference_number',
        'document_type', 'status', 'pricing_mode', 'salesperson_id', 'invoice_template_id',
        'converted_to_id', 'parent_invoice_id', 'quotation_status', 'quotation_response', 'quotation_responded_at', 'quotation_valid_until',
        'subtotal', 'charges_summary', 'discount', 'tax', 'round_off',
        'tds_rate', 'tds_amount', 'tcs_rate', 'tcs_amount',
        'grand_total', 'paid_amount', 'balance_amount',
        'notes', 'terms', 'attributes', 'created_by',
        'tax_mode', 'tax_breakdown', 'last_reminder_sent_at',
        'einvoice_status', 'irn', 'irn_ack_no', 'irn_ack_date', 'eway_bill_no',
    ];

    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'document_type' => DocumentType::class,
            'status' => InvoiceStatus::class,
            'pricing_mode' => PricingMode::class,
            'quotation_status' => QuotationStatus::class,
            'quotation_responded_at' => 'datetime',
            'quotation_valid_until' => 'date',
            'cancelled_at' => 'datetime',
            'charges_summary' => 'array',
            'tax_mode' => TaxMode::class,
            'attributes' => 'array',
            'tax_breakdown' => 'array',
            'last_reminder_sent_at' => 'datetime',
            'irn_ack_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'round_off' => 'decimal:2',
            'tds_rate' => 'decimal:2',
            'tds_amount' => 'decimal:2',
            'tcs_rate' => 'decimal:2',
            'tcs_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<InvoiceTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'invoice_template_id');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /**
     * Invoice-level dynamic charges (packing, delivery, ...) — see
     * InvoiceItem::charges() for the per-item equivalent.
     *
     * @return HasMany<InvoiceCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(InvoiceCharge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Agreed payment plan for this invoice's balance, if one was set.
     *
     * @return HasMany<Installment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<InvoiceShareLink, $this>
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(InvoiceShareLink::class);
    }

    /**
     * @return HasMany<InvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvoiceEvent::class);
    }

    /**
     * Notes recorded against this specific invoice. CustomerNote also carries
     * a customer_id, so the constraint must be explicit — an unconstrained
     * hasMany would return every note in the tenant.
     *
     * @return HasMany<CustomerNote, $this>
     */
    public function notesLog(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'invoice_id');
    }

    /**
     * The invoice this quotation was converted into, if any.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function convertedTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_to_id');
    }

    /**
     * The invoice a credit or debit note corrects, if any.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_invoice_id');
    }

    /**
     * Credit and debit notes issued against this invoice.
     *
     * @return HasMany<Invoice, $this>
     */
    public function adjustmentNotes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_invoice_id')
            ->whereIn('document_type', DocumentType::adjustmentValues());
    }

    /**
     * Net value the credit and debit notes move against this invoice:
     * negative when returns reduce what the customer owes, positive when a
     * debit note adds to it.
     */
    public function adjustmentsValue(): float
    {
        // A cancelled note must stop counting immediately, so the money is
        // always read fresh rather than from a possibly stale relation.
        $notes = Invoice::query()
            ->where('parent_invoice_id', $this->id)
            ->whereIn('document_type', DocumentType::adjustmentValues())
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->get(['document_type', 'grand_total']);

        return (float) $notes->sum(fn (self $note): float => match ($note->document_type) {
            DocumentType::CreditNote => -1 * (float) $note->grand_total,
            DocumentType::DebitNote => (float) $note->grand_total,
            default => 0.0,
        });
    }

    /**
     * Re-derive paid_amount, balance_amount, and status from recorded
     * payments. Item/charge totals are NOT touched here — those only
     * change via InvoiceCalculationService, run explicitly on
     * create/update. Does not persist — callers should save().
     */
    public function recalculatePaymentStatus(): void
    {
        // Quotations never carry payments; their draft/sent/accepted
        // lifecycle is managed explicitly, never derived from amounts.
        // Credit and debit notes are adjustments, not receivables: their
        // whole effect lands on the parent invoice's balance instead.
        if ($this->document_type === DocumentType::Quotation || $this->document_type->isAdjustment()) {
            return;
        }

        $this->paid_amount = $this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : (float) $this->payments()->sum('amount');

        // A return credited back can settle or exceed what was billed, so
        // the balance is grand total less payments, less credit notes, plus
        // debit notes - floored at zero (the excess is a refund to arrange
        // with the customer, not a negative invoice). TDS is withheld by the
        // buyer at settlement, so it is deducted here rather than inflating
        // a balance the customer will never pay in cash.
        $adjustments = $this->adjustmentsValue();

        $this->balance_amount = max(
            (float) $this->grand_total + $adjustments - (float) $this->tds_amount - (float) $this->paid_amount,
            0,
        );

        if ($this->status !== InvoiceStatus::Cancelled && $this->status !== InvoiceStatus::Refunded) {
            $settledByCredit = $adjustments < 0 && (float) $this->balance_amount <= 0;

            $this->status = match (true) {
                (float) $this->paid_amount <= 0 && ! $settledByCredit => $this->dueDateHasPassed() ? InvoiceStatus::Overdue : InvoiceStatus::Unpaid,
                (float) $this->balance_amount <= 0 => InvoiceStatus::Paid,
                default => InvoiceStatus::PartiallyPaid,
            };
        }
    }

    protected function dueDateHasPassed(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast();
    }
}
