<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Tenant;
use App\Services\QuotationFollowUpService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Nudges customers about quotations nobody has answered.
 *
 * Runs per tenant so every message_logs row is stamped with the shop it belongs
 * to, and so one tenant's bad contact data cannot stop the run for the rest.
 */
class SendQuotationFollowUps extends Command
{
    protected $signature = 'quotations:follow-up';

    protected $description = 'Nudge customers about open quotations they have not answered';

    public function __construct(private readonly QuotationFollowUpService $followUps)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            Tenant::runInContext($tenant->id, function () use ($tenant): void {
                $this->handleTenant($tenant->id);
            });
        }

        return self::SUCCESS;
    }

    protected function handleTenant(int $tenantId): void
    {
        $settings = BusinessSetting::forTenant($tenantId);

        if (! $settings->quotation_followup_enabled) {
            return;
        }

        $sent = 0;

        foreach ($this->followUps->pending() as $quotation) {
            try {
                if ($this->followUps->sendForQuotation($quotation) !== []) {
                    $sent++;
                }
            } catch (Throwable $e) {
                // One bad row must not stop the whole run.
                report($e);
            }
        }

        $this->info("{$settings->business_name}: {$sent} quotation follow-up(s) sent.");
    }
}
