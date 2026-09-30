<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
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
        $filters = $request->only(['search', 'customer_type', 'assigned_staff_id']);
        $type = $filters['customer_type'] ?? null;
        $staffId = $filters['assigned_staff_id'] ?? null;

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
        $customer = Customer::create([
            ...$request->validated(),
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
            'stats' => [
                'total_invoiced' => (float) $customer->totalInvoiced(),
                'total_paid' => (float) $customer->totalPaid(),
                'total_outstanding' => (float) $customer->totalOutstanding(),
                'last_invoice_date' => $customer->invoices()->max('invoice_date'),
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
        $customer->update($request->validated());

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
