<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

class SubscriptionService
{
    /**
     * Is the product currently free for every tenant?
     *
     * The single switch behind "all features are free": while true, no
     * tenant is ever gated and no quota is ever enforced. Set
     * BILLING_MODE=paid to turn the paywall back on without a code change.
     */
    public function isFreeMode(): bool
    {
        return config('billing.mode', 'free') !== 'paid';
    }

    public function subscriptionFor(Tenant $tenant): ?Subscription
    {
        return $tenant->subscription ?? $tenant->subscription()->first();
    }

    /**
     * The plan currently granting access, if any.
     *
     * The free plan is always usable regardless of subscription status —
     * while the product is free nobody should be redirected to checkout.
     */
    public function usablePlan(Tenant $tenant): ?Plan
    {
        // Free mode: everything is available to everyone, so access never
        // depends on a subscription row at all.
        if ($this->isFreeMode()) {
            return $this->defaultPlan();
        }

        $subscription = $this->subscriptionFor($tenant);

        // In paid mode a tenant with no subscription row has nothing to gate
        // against, so they fall back to the free plan rather than being
        // locked out of their own data.
        if (! $subscription) {
            return $this->defaultPlan();
        }

        if ($subscription->plan?->isFree()) {
            return $subscription->plan;
        }

        return $subscription->isUsable() ? $subscription->plan : null;
    }

    /**
     * The plan a tenant falls back to when nothing is recorded.
     */
    protected function defaultPlan(): ?Plan
    {
        // ensureDefaults is idempotent, and using it means a fresh
        // installation that has never seeded plans still gets access.
        return Plan::ensureDefaults()[Plan::FREE_SLUG] ?? null;
    }

    public function hasPaidPlan(Tenant $tenant): bool
    {
        $plan = $this->subscriptionFor($tenant)?->plan;

        return $plan !== null && ! $plan->isFree();
    }

    public function staffQuotaError(Tenant $tenant): ?string
    {
        // No quotas while the product is free.
        if ($this->isFreeMode()) {
            return null;
        }

        $plan = $this->usablePlan($tenant);

        if (! $plan) {
            return 'Your subscription is not active. Please renew to add staff.';
        }

        $count = User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        if ($plan->allowsUnlimitedStaff()) {
            return null;
        }

        if ($count >= $plan->max_staff) {
            return "Your {$plan->name} plan allows {$plan->max_staff} staff members. Upgrade to add more.";
        }

        return null;
    }

    public function invoiceQuotaError(Tenant $tenant): ?string
    {
        // No quotas while the product is free.
        if ($this->isFreeMode()) {
            return null;
        }

        $plan = $this->usablePlan($tenant);

        if (! $plan) {
            return 'Your subscription is not active. Please renew to create invoices.';
        }

        if ($plan->allowsUnlimitedInvoices()) {
            return null;
        }

        $count = Invoice::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($count >= $plan->max_invoices_per_month) {
            return "Your {$plan->name} plan allows {$plan->max_invoices_per_month} invoices per month. Upgrade for more.";
        }

        return null;
    }
}
