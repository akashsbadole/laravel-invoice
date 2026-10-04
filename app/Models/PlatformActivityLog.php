<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail for platform-level actions taken by a super admin.
 *
 * Not tenant-scoped: the actor has no tenant, and the subject usually does, so
 * the tenant is recorded as a plain filterable column rather than a global
 * scope.
 */
class PlatformActivityLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id',
        'tenant_id', 'description', 'properties', 'ip_address',
    ];

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string,mixed>  $properties
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $properties = [],
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            // A Tenant subject carries the id on tenant_id itself; a User subject does
            // too. Anything else (a Plan) has no tenant at all.
            'tenant_id' => $properties['tenant_id']
                ?? ($subject instanceof Tenant ? $subject->id : $subject?->tenant_id),
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * Human label for the log stream.
     */
    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    /**
     * @return array<string,string>
     */
    public static function actionLabels(): array
    {
        return [
            'tenant.suspended' => 'Tenant suspended',
            'tenant.reactivated' => 'Tenant reactivated',
            'tenant.impersonated' => 'Super admin impersonated tenant',
            'tenant.impersonation_ended' => 'Impersonation ended',
            'subscription.updated' => 'Subscription changed',
            'user.deactivated' => 'User deactivated',
            'user.activated' => 'User reactivated',
            'plan.updated' => 'Plan updated',
        ];
    }
}
