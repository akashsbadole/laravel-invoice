<?php

namespace App\Http\Controllers;

use App\Enums\CatalogStatus;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\CatalogItem;
use App\Models\Customer;
use App\Models\CustomerFollowup;
use App\Models\Invoice;
use App\Models\MetalRate;
use App\Models\Payment;
use App\Services\ReminderService;
use App\Support\Industry;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(ReminderService $reminders): Response
    {
        $today = today();
        $values = fn (array $statuses) => array_map(fn (InvoiceStatus $s) => $s->value, $statuses);

        $open = $values(InvoiceStatus::openStatuses());
        $counted = $values([InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid, InvoiceStatus::Overdue]);

        // Credit and debit notes are corrections of an invoice already
        // counted here, so they never appear as documents of their own.
        $withoutNotes = fn ($query) => $query
            ->whereNotIn('document_type', DocumentType::adjustmentValues());

        // Overdue = still owed and past its due date, regardless of whether the
        // nightly job has flipped the status yet.
        $overdue = fn () => Invoice::query()
            ->whereIn('status', $open)
            ->where('balance_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today);

        $stats = [
            'total_invoices' => $withoutNotes(Invoice::query())->count(),
            'invoices_this_month' => $withoutNotes(Invoice::query())
                ->whereBetween('invoice_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->count(),
            'paid' => Invoice::query()->where('status', InvoiceStatus::Paid->value)->count(),
            'unpaid' => Invoice::query()->where('status', InvoiceStatus::Unpaid->value)->count(),
            'partially_paid' => Invoice::query()->where('status', InvoiceStatus::PartiallyPaid->value)->count(),
            'overdue_count' => $overdue()->count(),
            'overdue_amount' => (float) $overdue()->sum('balance_amount'),
            'total_sales' => (float) Invoice::query()->whereIn('status', $counted)->sum('grand_total'),
            'total_collected' => (float) Invoice::query()->whereIn('status', $counted)->sum('paid_amount'),
            'total_outstanding' => (float) Invoice::query()->whereIn('status', $open)->sum('balance_amount'),
        ];

        $start = $today->copy()->startOfMonth()->subMonths(5);
        $sales = Invoice::query()->whereIn('status', $counted)->whereDate('invoice_date', '>=', $start)
            ->get(['id', 'invoice_date', 'grand_total'])
            ->groupBy(fn (Invoice $i) => $i->invoice_date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('grand_total'));
        $collected = Payment::query()->whereDate('payment_date', '>=', $start)
            ->get(['id', 'payment_date', 'amount'])
            ->groupBy(fn (Payment $p) => $p->payment_date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $months = collect(range(5, 0))->map(function (int $ago) use ($today, $sales, $collected) {
            $month = $today->copy()->startOfMonth()->subMonths($ago);
            $key = $month->format('Y-m');

            return ['label' => $month->format('M'), 'sales' => $sales[$key] ?? 0, 'collected' => $collected[$key] ?? 0];
        })->values();

        $due = $reminders->gather($today);

        $acceptedQuotesReadyToConvert = Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->where(function ($q) {
                $q->where('quotation_status', 'accepted')
                    ->orWhere('status', 'accepted');
            })
            ->whereNull('converted_to_id')
            ->with('customer:id,full_name,mobile_number')
            ->latest('invoice_date')
            ->get(['id', 'customer_id', 'invoice_number', 'invoice_date', 'status', 'grand_total', 'quotation_response', 'quotation_responded_at']);

        // Quotes sent more than two days ago that are still unanswered and
        // not converted — a gap in someone's memory today, a lost sale next
        // week. One row per quote so the owner can nudge the right person
        // instead of chasing everyone at random.
        $waitingOnCustomer = Invoice::query()
            ->where('document_type', DocumentType::Quotation->value)
            ->whereNull('converted_to_id')
            ->where(function ($q) {
                $q->where('quotation_status', 'sent')->orWhere('status', 'sent');
            })
            ->whereDate('invoice_date', '<=', $today->copy()->subDays(2))
            ->with([
                'customer:id,full_name,mobile_number',
                'shareLinks' => fn ($q) => $q->where('is_active', true)->latest(),
            ])
            ->orderBy('invoice_date')
            ->limit(8)
            ->get(['id', 'customer_id', 'invoice_number', 'invoice_date', 'status', 'grand_total', 'quotation_status'])
            ->map(fn (Invoice $quotation) => [
                'id' => $quotation->id,
                'invoice_number' => $quotation->invoice_number,
                'customer' => $quotation->customer?->full_name ?? '—',
                'mobile_number' => $quotation->customer?->mobile_number ?? '',
                'grand_total' => (float) $quotation->grand_total,
                'age_days' => min((int) ($quotation->invoice_date?->diffInDays($today) ?? 0), 365),
                'viewed_at' => $quotation->shareLinks->first()?->viewed_at,
                'token' => $quotation->shareLinks->first()?->token,
            ]);

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'months' => $months,
            'acceptedQuotationsToConvert' => $acceptedQuotesReadyToConvert,
            'waitingOnCustomer' => $waitingOnCustomer,
            'recentInvoices' => $withoutNotes(Invoice::query())->with('customer:id,full_name')->latest()->limit(5)
                ->get(['id', 'customer_id', 'invoice_number', 'invoice_date', 'status', 'grand_total', 'balance_amount']),
            'recentCustomers' => Customer::query()->latest()->limit(5)->get(['id', 'full_name', 'mobile_number', 'created_at']),
            'upcomingFollowups' => CustomerFollowup::query()
                ->with(['customer:id,full_name', 'assignee:id,name'])
                ->whereIn('status', ReminderService::OPEN_FOLLOWUP_STATUSES)
                ->whereDate('followup_date', '<=', $today->copy()->addDays(7))
                ->orderBy('followup_date')->limit(6)->get(),
            'overdueInvoices' => $overdue()->with('customer:id,full_name')->orderBy('due_date')->limit(5)
                ->get(['id', 'customer_id', 'invoice_number', 'due_date', 'balance_amount']),
            // Metal rates only mean something to a jewelry tenant.
            'rates' => Industry::usesMetalRates()
                ? MetalRate::latestRates()->take(8)->values()
                : collect(),
            // The catalog is the entry point for quotations, so surface how
            // stocked it is rather than hiding it in Settings.
            'catalog' => [
                'total' => CatalogItem::query()->count(),
                'active' => CatalogItem::query()->where('status', CatalogStatus::Active->value)->count(),
                'drafts' => CatalogItem::query()->where('status', CatalogStatus::Draft->value)->count(),
                'recent' => CatalogItem::query()->latest('id')->limit(5)->get(['id', 'name', 'brand', 'rate_type', 'default_rate']),
            ],
            'reminderCount' => $due['payments']->count() + $due['followups']->count() + $due['birthdays']->count()
                + $due['anniversaries']->count() + $due['custom']->count(),
        ]);
    }
}
