<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerFollowup;
use App\Models\Invoice;
use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gathers everything that needs attention. Payment, follow-up, birthday and
 * anniversary reminders are derived live from real data, so they can never
 * drift out of sync; only custom reminders are stored.
 */
class ReminderService
{
    public const OPEN_FOLLOWUP_STATUSES = ['pending', 'contacted', 'waiting_for_response'];

    /**
     * @return array{payments:Collection,followups:Collection,birthdays:Collection,anniversaries:Collection,custom:Collection}
     */
    public function gather(?Carbon $today = null, int $days = 7): array
    {
        $today = ($today ?? today())->copy()->startOfDay();
        $open = array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::openStatuses());

        return [
            'payments' => Invoice::query()
                ->with('customer:id,full_name,mobile_number')
                ->whereIn('status', $open)
                ->where('balance_amount', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $today->copy()->addDays(3))
                ->orderBy('due_date')
                ->get(),
            'followups' => CustomerFollowup::query()
                ->with(['customer:id,full_name,mobile_number', 'assignee:id,name'])
                ->whereIn('status', self::OPEN_FOLLOWUP_STATUSES)
                ->whereDate('followup_date', '<=', $today->copy()->addDay())
                ->orderBy('followup_date')
                ->get(),
            'birthdays' => $this->upcoming('birthday', $today, $days),
            'anniversaries' => $this->upcoming('anniversary', $today, $days),
            'custom' => Reminder::query()
                ->with('customer:id,full_name,mobile_number')
                ->where('is_done', false)
                ->whereDate('remind_on', '<=', $today->copy()->addDay())
                ->orderBy('remind_on')
                ->get(),
        ];
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    public function upcoming(string $column, Carbon $today, int $days): Collection
    {
        return Customer::query()
            ->whereNotNull($column)
            ->get(['id', 'full_name', 'mobile_number', $column])
            ->map(function (Customer $customer) use ($column, $today, $days) {
                $date = $customer->{$column};

                if (! $date) {
                    return null;
                }

                $next = $date->copy()->year($today->year)->startOfDay();

                if ($next->lt($today)) {
                    $next->addYear();
                }

                $until = (int) floor($today->diffInDays($next));

                return $until <= $days ? [
                    'id' => $customer->id,
                    'full_name' => $customer->full_name,
                    'mobile_number' => $customer->mobile_number,
                    'date' => $date->format('Y-m-d'),
                    'days_until' => $until,
                ] : null;
            })
            ->filter()
            ->sortBy('days_until')
            ->values();
    }
}
