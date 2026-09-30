<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringProfile extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'customer_id', 'source_invoice_id', 'salesperson_id',
        'frequency', 'next_run_at', 'last_run_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'next_run_at' => 'date',
            'last_run_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function advance(): void
    {
        $this->last_run_at = today()->toDateString();
        $this->next_run_at = match ($this->frequency) {
            'weekly' => today()->addWeek(),
            'quarterly' => today()->addMonths(3),
            default => today()->addMonth(),
        };
        $this->save();
    }
}
