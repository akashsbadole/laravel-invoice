<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvoiceShareLink extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = ['invoice_id', 'expires_at', 'is_active', 'sent_via', 'sent_at', 'created_by'];

    /** @var list<string> */
    protected $hidden = ['password_hash'];

    /**
     * Serialise the "is it protected" flag so staff can warn the customer.
     * Only the boolean — the hash is never sent to a browser.
     *
     * @var list<string>
     */
    protected $appends = ['has_password'];

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
     * Whether a customer must type a password before they can open this link.
     *
     * The staff screen needs to know this so it can warn them to pass the
     * password on separately — otherwise they share the link and lock the
     * customer out. Derived rather than stored, and safe to serialise: the
     * hash itself stays hidden.
     */
    protected function hasPassword(): Attribute
    {
        return Attribute::get(fn (): bool => filled($this->password_hash));
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
