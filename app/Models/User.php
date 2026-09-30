<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    /** @use HasFactory<UserFactory> */
    use BelongsToTenant;

    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function customersCreated(): HasMany
    {
        return $this->hasMany(Customer::class, 'created_by');
    }

    /**
     * @return HasMany<Customer, $this>
     */
    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoicesCreated(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoicesAsSalesperson(): HasMany
    {
        return $this->hasMany(Invoice::class, 'salesperson_id');
    }

    /**
     * @return HasMany<CustomerFollowup, $this>
     */
    public function assignedFollowups(): HasMany
    {
        return $this->hasMany(CustomerFollowup::class, 'assigned_to');
    }
}
