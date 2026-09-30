<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['channel', 'driver', 'to', 'body', 'status', 'error', 'customer_id', 'invoice_id', 'created_by'];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
