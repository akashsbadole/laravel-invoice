<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'full_name', 'mobile_number', 'email', 'address', 'tax_number',
        'state_code', 'birthday', 'anniversary',
        'notes', 'customer_type', 'assigned_staff_id', 'created_by',
    ];

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'birthday' => 'date:Y-m-d',
            'anniversary' => 'date:Y-m-d',
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

    public function totalInvoiced(): string
    {
        return (string) $this->invoices()->sum('grand_total');
    }

    public function totalPaid(): string
    {
        return (string) $this->invoices()->sum('paid_amount');
    }

    public function totalOutstanding(): string
    {
        return (string) $this->invoices()->sum('balance_amount');
    }
}
