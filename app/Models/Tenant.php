<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    /**
     * @return array<string, class-string<Factory>>
     */
    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    /** @var list<string> */
    protected $fillable = ['name', 'industry', 'slug', 'status', 'trial_ends_at'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static ?int $contextId = null;

    protected static bool $resolving = false;

    /**
     * The tenant of the currently authenticated user, if any.
     * Guest contexts (public share links) resolve to null and tenant
     * scopes stay open for them. Console commands should wrap work in
     * runInContext() so background jobs stay tenant-scoped.
     *
     * The re-entrancy guard matters: resolving auth()->user() runs user
     * queries, which themselves carry the tenant scope. Without it the
     * login flow recurses until memory exhaustion (blank HTTP 500).
     */
    public static function currentId(): ?int
    {
        if (static::$resolving) {
            return static::$contextId;
        }

        static::$resolving = true;

        try {
            return static::$contextId ?? auth()->user()?->tenant_id;
        } finally {
            static::$resolving = false;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runInContext(?int $tenantId, callable $callback): mixed
    {
        $previous = static::$contextId;
        static::$contextId = $tenantId;

        try {
            return $callback();
        } finally {
            static::$contextId = $previous;
        }
    }

    public static function current(): ?self
    {
        $id = static::currentId();

        return $id ? static::query()->find($id) : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<BusinessSetting, $this>
     */
    public function businessSetting(): HasOne
    {
        return $this->hasOne(BusinessSetting::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
