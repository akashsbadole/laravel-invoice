<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const FREE_SLUG = 'free';

    /** @var list<string> */
    protected $fillable = [
        'name', 'slug', 'price', 'currency', 'interval',
        'max_staff', 'max_invoices_per_month', 'features',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_staff' => 'integer',
            'max_invoices_per_month' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function allowsUnlimitedInvoices(): bool
    {
        return $this->max_invoices_per_month === null;
    }

    public function allowsUnlimitedStaff(): bool
    {
        return $this->max_staff >= 1000000;
    }

    /**
     * The always-available plan. While the product is free every new tenant
     * lands here, so nothing is gated behind checkout.
     */
    public function isFree(): bool
    {
        return $this->slug === self::FREE_SLUG;
    }

    /**
     * Seed the default plans if missing and return them keyed by slug.
     *
     * @return array<string, self>
     */
    public static function ensureDefaults(): array
    {
        $defaults = [
            [
                'name' => 'Free', 'slug' => self::FREE_SLUG, 'price' => 0,
                'max_staff' => 1000000, 'max_invoices_per_month' => null,
                'features' => ['Unlimited staff', 'Unlimited invoices', 'Catalog + stock', 'PDF, share links & WhatsApp'],
                'sort_order' => 0,
            ],
            [
                'name' => 'Starter', 'slug' => 'starter', 'price' => 499,
                'max_staff' => 5, 'max_invoices_per_month' => 200,
                'features' => ['Up to 5 staff', '200 invoices / month', 'PDF + share links', 'Email support'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional', 'slug' => 'professional', 'price' => 999,
                'max_staff' => 20, 'max_invoices_per_month' => 1000,
                'features' => ['Up to 20 staff', '1,000 invoices / month', 'PDF + share links + SMS', 'Priority support'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 2499,
                'max_staff' => 1000000, 'max_invoices_per_month' => null,
                'features' => ['Unlimited staff', 'Unlimited invoices', 'Everything in Professional', 'Dedicated support'],
                'sort_order' => 3,
            ],
        ];

        $plans = [];

        foreach ($defaults as $attributes) {
            $plans[$attributes['slug']] = static::query()->firstOrCreate(
                ['slug' => $attributes['slug']],
                $attributes,
            );
        }

        return $plans;
    }
}
