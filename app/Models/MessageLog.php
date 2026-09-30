<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['channel', 'driver', 'to', 'body', 'status', 'error', 'customer_id', 'invoice_id', 'created_by'])]
class MessageLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
