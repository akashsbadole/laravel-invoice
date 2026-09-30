<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\TaxMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'customer_id', 'invoice_number', 'invoice_date', 'due_date', 'reference_number',
    'status', 'pricing_mode', 'salesperson_id', 'invoice_template_id',
    'subtotal', 'charges_summary', 'discount', 'tax', 'round_off',
    'grand_total', 'paid_amount', 'balance_amount',
    'notes', 'terms', 'created_by',
    'tax_mode', 'tax_breakdown', 'last_reminder_sent_at',
])]
class Invoice extends Model
{
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
            'status' => InvoiceStatus::class,
            'pricing_mode' => PricingMode::class,
            'cancelled_at' => 'datetime',
            'charges_summary' => 'array',
            'tax_mode' => TaxMode::class,
            'tax_breakdown' => 'array',
            'last_reminder_sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'round_off' => 'decimal:2',
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
     * @return HasMany<CustomerNote, $this>
     */
    public function notesLog(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Re-derive paid_amount, balance_amount, and status from recorded
     * payments. Item/charge totals are NOT touched here — those only
     * change via InvoiceCalculationService, run explicitly on
     * create/update. Does not persist — callers should save().
     */
    public function recalculatePaymentStatus(): void
    {
        $this->paid_amount = $this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : (float) $this->payments()->sum('amount');

        $this->balance_amount = max((float) $this->grand_total - (float) $this->paid_amount, 0);

        if ($this->status !== InvoiceStatus::Cancelled && $this->status !== InvoiceStatus::Refunded) {
            $this->status = match (true) {
                (float) $this->paid_amount <= 0 => $this->dueDateHasPassed() ? InvoiceStatus::Overdue : InvoiceStatus::Unpaid,
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
