<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AdvanceStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAdvance extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'amount', 'applied_amount', 'advance_date',
        'payment_method', 'reference_number', 'notes', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'advance_date' => 'date',
            'payment_method' => PaymentMethod::class,
            'status' => AdvanceStatus::class,
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The slice of this advance that has not been consumed yet.
     */
    public function availableAmount(): float
    {
        return round((float) $this->amount - (float) $this->applied_amount, 2);
    }

    public function isAvailable(): bool
    {
        return $this->status === AdvanceStatus::Available && $this->availableAmount() > 0;
    }
}
