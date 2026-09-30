<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

class SubscriptionService
{
    public function subscriptionFor(Tenant $tenant): ?Subscription
    {
        return $tenant->subscription ?? $tenant->subscription()->first();
    }

    public function usablePlan(Tenant $tenant): ?Plan
    {
        $subscription = $this->subscriptionFor($tenant);

        if (! $subscription || ! $subscription->isUsable()) {
            return null;
        }

        return $subscription->plan;
    }

    public function staffQuotaError(Tenant $tenant): ?string
    {
        $plan = $this->usablePlan($tenant);

        if (! $plan) {
            return 'Your subscription is not active. Please renew to add staff.';
        }

        $count = User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        if ($count >= $plan->max_staff) {
            return "Your {$plan->name} plan allows {$plan->max_staff} staff members. Upgrade to add more.";
        }

        return null;
    }

    public function invoiceQuotaError(Tenant $tenant): ?string
    {
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
