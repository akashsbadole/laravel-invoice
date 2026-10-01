<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled slice of an invoice balance — the "pay in 3" or "advance
 * booking" plan agreed with a customer.
 */
class Installment extends Model
{
    use BelongsToTenant;

    protected $table = 'invoice_installments';

    /** @var list<string> */
    protected $fillable = [
        'invoice_id', 'sequence', 'due_date', 'amount',
        'status', 'payment_id', 'paid_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The payment that settled this installment, if it has been paid.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === InstallmentStatus::Pending
            && $this->due_date->isPast();
    }
}
