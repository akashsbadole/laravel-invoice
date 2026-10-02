<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\User;
use App\Support\Attributes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'customer_type', 'assigned_staff_id', 'tag']);
        $type = $filters['customer_type'] ?? null;
        $staffId = $filters['assigned_staff_id'] ?? null;
        $tag = trim((string) ($filters['tag'] ?? ''));

        $customers = Customer::query()
            ->with('assignedStaff:id,name')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($type && $type !== 'all', fn ($query) => $query->where('customer_type', $type))
            ->when($staffId && $staffId !== 'all', fn ($query) => $query->where('assigned_staff_id', $staffId))
            ->when($tag !== '', function ($query) use ($tag) {
                // Tags are stored as a JSON array. Match the exact encoded tag
                // so "bridal" does not also match "bridal-wear". LIKE wildcards
                // in the tag itself are escaped for both MySQL and SQLite.
                $needle = (string) json_encode($tag);
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);

                $query->whereRaw('customers.tags LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
            })
            ->withSum('invoices as total_invoiced', 'grand_total')
            ->withSum('invoices as total_outstanding', 'balance_amount')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => $filters,
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('customers/create', [
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::create([
            ...$data,
            // The editor posts indexed [key, value] rows; flatten to a map.
            'attributes' => Attributes::clean($data['attributes'] ?? []),
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record('customer.created', $customer, "Created customer {$customer->full_name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'assignedStaff:id,name',
            'invoices' => fn ($query) => $query->latest('invoice_date')->limit(10),
            'notesLog' => fn ($query) => $query->with('creator:id,name')->latest(),
            'followups' => fn ($query) => $query->with('assignee:id,name')->orderBy('followup_date'),
        ]);

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'timeline' => $this->timeline($customer),
            'stats' => [
                'total_invoiced' => (float) $customer->totalInvoiced(),
                'total_paid' => (float) $customer->totalPaid(),
                'total_outstanding' => (float) $customer->totalOutstanding(),
                'last_invoice_date' => $customer->invoices()->max('invoice_date'),
                // Credit position, so the page can show how close this customer
                // is to their limit without the UI re-deriving it.
                'credit_limit' => $customer->credit_limit !== null ? (float) $customer->credit_limit : null,
                'credit_outstanding' => $customer->creditOutstanding(),
                'credit_overrun' => $customer->creditOverrun(),
            ],
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('customers/edit', [
            'customer' => $customer,
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();

        $customer->update([
            ...$data,
            'attributes' => Attributes::clean($data['attributes'] ?? []),
        ]);

        ActivityLog::record('customer.updated', $customer, "Updated customer {$customer->full_name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('customers.show', $customer);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        ActivityLog::record('customer.deleted', $customer, "Deleted customer {$customer->full_name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return to_route('customers.index');
    }

    /**
     * A merged, reverse-chronological history for one customer: invoices,
     * payments, notes, follow-ups, messages and the shared activity log.
     *
     * @return list<array{at:string,kind:string,label:string,detail:string|null,href:string|null}>
     */
    public function timeline(Customer $customer): array
    {
        $events = [];

        foreach ($customer->invoices()->latest('invoice_date')->get() as $invoice) {
            $events[] = [
                'at' => (string) $invoice->invoice_date,
                'kind' => 'invoice',
                'label' => "{$invoice->invoice_number} · ".number_format((float) $invoice->grand_total, 2),
                'detail' => $invoice->document_type->value,
                'href' => route('invoices.show', $invoice),
            ];
        }

        foreach (Payment::query()->whereIn('invoice_id', $customer->invoices()->select('id'))->latest('payment_date')->get() as $payment) {
            $events[] = [
                'at' => (string) $payment->payment_date,
                'kind' => 'payment',
                'label' => 'Payment received '.number_format((float) $payment->amount, 2),
                'detail' => $payment->payment_method->value,
                'href' => route('invoices.show', $payment->invoice_id),
            ];
        }

        foreach ($customer->notesLog()->with('creator:id,name')->latest()->get() as $note) {
            $events[] = [
                'at' => (string) $note->created_at,
                'kind' => 'note',
                'label' => $note->note,
                'detail' => $note->creator?->name,
                'href' => null,
            ];
        }

        foreach ($customer->followups()->with('assignee:id,name')->latest('followup_date')->get() as $followup) {
            $events[] = [
                'at' => (string) $followup->followup_date,
                'kind' => 'followup',
                'label' => 'Follow-up · '.$followup->status->value,
                'detail' => $followup->notes,
                'href' => null,
            ];
        }

        foreach (MessageLog::query()->where('customer_id', $customer->id)->latest()->limit(20)->get() as $log) {
            $events[] = [
                'at' => (string) $log->created_at,
                'kind' => 'message',
                'label' => "{$log->channel} to {$log->to} · {$log->status}",
                'detail' => $log->body,
                'href' => null,
            ];
        }

        foreach (ActivityLog::query()
            ->where('subject_type', $customer->getMorphClass())
            ->where('subject_id', $customer->id)
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get() as $entry) {
            $events[] = [
                'at' => (string) $entry->created_at,
                'kind' => 'activity',
                'label' => $entry->description ?? $entry->action,
                'detail' => $entry->user?->name,
                'href' => null,
            ];
        }

        usort($events, fn (array $a, array $b) => strcmp($b['at'], $a['at']));

        return array_slice($events, 0, 50);
    }

    public function exportCsv(Request $request): HttpResponse
    {
        Gate::authorize('export', Customer::class);

        $customers = Customer::query()
            ->when($request->search, fn ($query, $search) => $query->where('full_name', 'like', "%{$search}%"))
            ->get();

        $rows = ['Name,Mobile,Email,Type,Total Invoiced,Total Paid,Total Outstanding'];

        foreach ($customers as $customer) {
            $rows[] = implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                [
                    $customer->full_name,
                    $customer->mobile_number,
                    $customer->email,
                    $customer->customer_type,
                    $customer->totalInvoiced(),
                    $customer->totalPaid(),
                    $customer->totalOutstanding(),
                ],
            ));
        }

        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="customers.csv"',
        ]);
    }
}
