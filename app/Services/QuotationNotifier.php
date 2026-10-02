<?php

namespace App\Services;

use App\Enums\QuotationActivity;
use App\Enums\UserRole;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\QuotationActivityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the shop what a customer did with a quotation.
 *
 * Every call is best-effort. A customer opening or accepting a shared quotation
 * must never be blocked — or slowed — by an alert that fails to send, so nothing
 * here is allowed to throw into the request.
 */
class QuotationNotifier
{
    /**
     * @return int how many staff members were notified
     */
    public function activity(Invoice $quotation, QuotationActivity $activity, ?string $detail = null): int
    {
        if ($quotation->tenant_id === null) {
            return 0;
        }

        $settings = BusinessSetting::forTenant($quotation->tenant_id);

        if (! $settings->quotation_alerts_owner) {
            return 0;
        }

        $quotation->loadMissing('customer');

        $notification = QuotationActivityNotification::fromInvoice(
            $activity,
            $quotation,
            $detail,
            $settings->quotation_alerts_email ? ['database', 'mail'] : ['database'],
        );

        $notified = 0;

        foreach ($this->recipients($quotation) as $user) {
            try {
                $user->notify($notification);
                $notified++;
            } catch (Throwable $e) {
                // A misconfigured mailer must not surface as a 500 on the
                // customer's share link.
                Log::warning("Quotation alert to {$user->email} failed: {$e->getMessage()}");
            }
        }

        return $notified;
    }

    /**
     * The staff who can act on a customer's answer.
     *
     * Read-only viewers are deliberately excluded: they cannot reply, convert
     * or re-quote, so alerting them is noise.
     *
     * The tenant is filtered explicitly rather than relying on TenantScope,
     * because this runs on a guest request (the customer's public share link)
     * where there is no tenant context and the global scope is a no-op.
     *
     * @return Collection<int, User>
     */
    protected function recipients(Invoice $quotation): Collection
    {
        return User::query()
            ->where('tenant_id', $quotation->tenant_id)
            ->where('is_active', true)
            ->whereIn('role', [
                UserRole::Admin->value,
                UserRole::Manager->value,
                UserRole::InvoiceCreator->value,
            ])
            ->get();
    }
}
