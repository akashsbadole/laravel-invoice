<?php

namespace App\Http\Controllers;

use App\Enums\FollowupStatus;
use App\Http\Requests\Customers\StoreCustomerFollowupRequest;
use App\Http\Requests\Customers\UpdateCustomerFollowupRequest;
use App\Models\Customer;
use App\Models\CustomerFollowup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CustomerFollowupController extends Controller
{
    public function store(StoreCustomerFollowupRequest $request, Customer $customer): RedirectResponse
    {
        $customer->followups()->create([
            ...$request->validated(),
            'status' => FollowupStatus::Pending,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-up scheduled.')]);

        return back();
    }

    public function update(UpdateCustomerFollowupRequest $request, Customer $customer, CustomerFollowup $followup): RedirectResponse
    {
        abort_unless($followup->customer_id === $customer->id, 404);

        $followup->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Follow-up updated.')]);

        return back();
    }
}
