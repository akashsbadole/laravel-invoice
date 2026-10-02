<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerGroups\StoreCustomerGroupRequest;
use App\Http\Requests\CustomerGroups\UpdateCustomerGroupRequest;
use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()->canDo(Permission::ManageSettings), 403);

        return Inertia::render('settings/customer-groups', [
            'customerGroups' => CustomerGroup::query()
                ->withCount('customers')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreCustomerGroupRequest $request): RedirectResponse
    {
        CustomerGroup::create([
            ...$request->validated(),
            'is_active' => true,
            'sort_order' => CustomerGroup::query()->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group added.')]);

        return back();
    }

    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): RedirectResponse
    {
        $customerGroup->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group updated.')]);

        return back();
    }

    public function destroy(CustomerGroup $customerGroup): RedirectResponse
    {
        abort_unless(request()->user()->canDo(Permission::ManageSettings), 403);

        // Cleared in the query rather than left to a foreign key: SQLite (the
        // test driver) cannot add constraints to a table that already exists,
        // so relying on ON DELETE SET NULL would only work in production.
        Customer::query()
            ->where('customer_group_id', $customerGroup->id)
            ->update(['customer_group_id' => null]);

        $customerGroup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer group removed.')]);

        return back();
    }
}
