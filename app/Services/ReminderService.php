<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\CustomerFollowup;
use App\Models\Invoice;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Builder;
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
     * @return array{payments:Collection,followups:Collection,birthdays:Collection,anniversaries:Collection,custom:Collection,expiring_quotes:Collection}
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
            // A follow-up surfaces when EITHER its scheduled date has arrived or its
            // explicit reminder_at has passed — reminder_at lets staff be
            // pinged ahead of the actual call.
            'followups' => CustomerFollowup::query()
                ->with(['customer:id,full_name,mobile_number', 'assignee:id,name'])
                ->whereIn('status', self::OPEN_FOLLOWUP_STATUSES)
                ->where(function ($query) use ($today): void {
                    $deadline = $today->copy()->addDay();

                    $query
                        ->whereDate('followup_date', '<=', $deadline)
                        ->orWhere('reminder_at', '<=', $deadline);
                })
                ->orderByRaw('COALESCE(reminder_at, followup_date)')
                ->get(),
            'birthdays' => $this->upcoming('birthday', $today, $days),
            'anniversaries' => $this->upcoming('anniversary', $today, $days),
            'custom' => Reminder::query()
                ->with('customer:id,full_name,mobile_number')
                ->where('is_done', false)
                ->whereDate('remind_on', '<=', $today->copy()->addDay())
                ->orderBy('remind_on')
                ->get(),
            // Staff need to chase a quote before its window closes, not after.
            'expiring_quotes' => Invoice::query()
                ->with('customer:id,full_name,mobile_number')
                ->where('document_type', DocumentType::Quotation->value)
                ->whereNotNull('quotation_valid_until')
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('quotation_status')
                        ->orWhereIn('quotation_status', [
                            QuotationStatus::Draft->value,
                            QuotationStatus::Sent->value,
                        ]);
                })
                ->whereDate('quotation_valid_until', '<=', $today->copy()->addDays(3))
                ->orderBy('quotation_valid_until')
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
