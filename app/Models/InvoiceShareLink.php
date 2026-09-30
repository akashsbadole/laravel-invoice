<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['invoice_id', 'expires_at', 'is_active', 'sent_via', 'sent_at', 'created_by'])]
#[Hidden(['password_hash'])]
class InvoiceShareLink extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'downloaded_at' => 'datetime',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function setPassword(?string $plainPassword): void
    {
        $this->password_hash = $plainPassword ? Hash::make($plainPassword) : null;
    }

    public function checkPassword(string $plainPassword): bool
    {
        return $this->password_hash && Hash::check($plainPassword, $this->password_hash);
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function markViewed(): void
    {
        $this->forceFill(['viewed_at' => $this->viewed_at ?? now()])->save();
    }

    public function markDownloaded(): void
    {
        $this->forceFill(['downloaded_at' => now()])->save();
    }
}
