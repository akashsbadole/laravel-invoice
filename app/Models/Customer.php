<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AdvanceStatus;
use App\Enums\ContactChannel;
use App\Enums\DocumentType;
use App\Enums\GstinType;
use App\Enums\InvoiceStatus;
use App\Enums\PriceTier;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Customer extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @return array<string, class-string<Factory>>
     */
    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /** @var list<string> */
    protected $fillable = [
        'full_name', 'mobile_number', 'email', 'address', 'tax_number',
        'gstin_type', 'state_code', 'place_of_supply',
        'credit_limit', 'credit_days', 'price_tier', 'preferred_contact_channel',
        'referral_source', 'tags',
        'birthday', 'anniversary', 'attributes',
        'notes', 'customer_type', 'assigned_staff_id', 'created_by',
    ];

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthday' => 'date:Y-m-d',
            'anniversary' => 'date:Y-m-d',
            'attributes' => 'array',
            'tags' => 'array',
            'gstin_type' => GstinType::class,
            'price_tier' => PriceTier::class,
            'preferred_contact_channel' => ContactChannel::class,
            'credit_limit' => 'decimal:2',
            'credit_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Named notesLog (not notes) — this model already has a plain
     * `notes` text column, and Eloquent would otherwise collide the
     * two under the same key when serialized to JSON.
     *
     * @return HasMany<CustomerNote, $this>
     */
    public function notesLog(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * @return HasMany<CustomerFollowup, $this>
     */
    public function followups(): HasMany
    {
        return $this->hasMany(CustomerFollowup::class);
    }

    /**
     * Booking / advance payments taken before an invoice existed.
     *
     * @return HasMany<CustomerAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(CustomerAdvance::class);
    }

    /**
     * Money this customer has paid ahead that no invoice has consumed yet.
     */
    public function availableAdvance(): float
    {
        return round(
            (float) $this->advances()
                ->where('status', AdvanceStatus::Available->value)
                ->sum('amount')
            - (float) $this->advances()
                ->where('status', AdvanceStatus::Available->value)
                ->sum('applied_amount'),
            2,
        );
    }

    public function totalInvoiced(): string
    {
        $net = (float) $this->invoices()
            ->whereNotIn('document_type', DocumentType::adjustmentValues())
            ->sum('grand_total');

        // Returns credited back reduce what this customer was billed for;
        // debit notes add to it. A cancelled note must not move the figure.
        $noteEffect = (float) $this->invoices()
            ->whereIn('document_type', DocumentType::adjustmentValues())
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->get()
            ->sum(fn (Invoice $note): float => $note->document_type === DocumentType::CreditNote
                ? -1 * (float) $note->grand_total
                : (float) $note->grand_total);

        return (string) round($net + $noteEffect, 2);
    }

    public function totalPaid(): string
    {
        return (string) $this->invoices()->sum('paid_amount');
    }

    public function totalOutstanding(): string
    {
        return (string) $this->invoices()->sum('balance_amount');
    }

    /**
     * Outstanding balance this business is willing to carry for this customer.
     *
     * Only payable documents count: a quotation is a proposal and a delivery
     * challan is a goods movement, so neither is money owed.
     */
    public function creditOutstanding(): float
    {
        $saleTypes = array_column(
            array_filter(DocumentType::cases(), fn (DocumentType $type) => $type->isSale()),
            'value',
        );

        return (float) $this->invoices()
            ->whereIn('document_type', $saleTypes)
            ->whereNotIn('status', [InvoiceStatus::Cancelled->value, InvoiceStatus::Refunded->value])
            ->sum('balance_amount');
    }

    /**
     * Whether this customer extends credit at all.
     */
    public function hasCreditLimit(): bool
    {
        return $this->credit_limit !== null;
    }

    /**
     * How far over their credit limit this customer currently is.
     *
     * Zero or less means within limit. Null means the business does not
     * extend credit, so there is nothing to compare against.
     */
    public function creditOverrun(): ?float
    {
        if (! $this->hasCreditLimit()) {
            return null;
        }

        $over = $this->creditOutstanding() - (float) $this->credit_limit;

        return $over > 0 ? round($over, 2) : 0.0;
    }

    /**
     * Whether invoicing this customer would exceed their credit limit.
     *
     * @param  float  $additional  the value of the document being created
     */
    public function exceedsCreditLimit(float $additional = 0.0): bool
    {
        if (! $this->hasCreditLimit()) {
            return false;
        }

        return ($this->creditOutstanding() + $additional) > (float) $this->credit_limit;
    }

    /**
     * The date payment is due, derived from this customer's credit days.
     *
     * Null when they have no credit terms, in which case the invoice keeps
     * whatever due date staff entered.
     */
    public function creditDueDate(?Carbon $from = null): ?Carbon
    {
        if ($this->credit_days === null) {
            return null;
        }

        return Carbon::parse($from ?? now())->addDays($this->credit_days)->startOfDay();
    }

    /**
     * The state code an e-invoice should report as place of supply.
     *
     * An explicit place_of_supply wins over the customer's home state, because
     * goods are frequently delivered somewhere else.
     */
    public function effectivePlaceOfSupply(): ?string
    {
        return $this->place_of_supply ?? $this->state_code;
    }

    /**
     * Whether this customer's transactions are B2B for e-invoicing.
     *
     * An explicit gstin_type decides it. When it is not set, fall back to
     * whether the customer actually has a GSTIN, which is the pre-existing
     * behaviour.
     */
    public function isBusinessBuyer(): bool
    {
        if ($this->gstin_type !== null) {
            return $this->gstin_type->isBusiness();
        }

        return filled($this->tax_number);
    }
}
