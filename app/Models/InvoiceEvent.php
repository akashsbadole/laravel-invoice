<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\InvoiceEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEvent extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['invoice_id', 'event_type', 'meta', 'caused_by'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event_type' => InvoiceEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by');
    }

    public static function log(Invoice $invoice, InvoiceEventType $type, array $meta = [], ?int $causer = null): self
    {
        return static::create([
            'invoice_id' => $invoice->id,
            'event_type' => $type,
            'meta' => $meta,
            'caused_by' => $causer,
        ]);
    }
}
