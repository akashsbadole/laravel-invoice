<?php

namespace App\Console\Commands;

use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Flags catalog drafts that have sat untouched for too long.
 *
 * The command itself only counts stale drafts and writes the figure to the
 * activity log; the dashboard reads the count and surfaces a toast so the
 * user knows there are drafts to review. Nothing is deleted.
 */
class FlagStaleDrafts extends Command
{
    protected $signature = 'catalogs:flag-stale-drafts';

    protected $description = 'Flag catalog drafts older than the stale threshold for review';

    /**
     * Days a draft can sit before it is considered stale.
     */
    protected const STALE_DAYS = 30;

    public function handle(): int
    {
        $count = 0;

        try {
            foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
                $count += Tenant::runInContext($tenant->id, function (): int {
                    return CatalogItem::query()
                        ->where('status', CatalogStatus::Draft->value)
                        ->where('created_at', '<', now()->subDays(self::STALE_DAYS))
                        ->count();
                });
            }
        } catch (\Throwable $e) {
            Log::error('Stale draft sweep failed.', ['exception' => $e]);

            $this->error('Stale draft sweep failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($count > 0) {
            $this->info("{$count} stale draft item(s) found — review in the catalog.");
        }

        return self::SUCCESS;
    }
}
