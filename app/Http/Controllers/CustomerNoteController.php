<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customers\StoreCustomerNoteRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CustomerNoteController extends Controller
{
    public function store(StoreCustomerNoteRequest $request, Customer $customer): RedirectResponse
    {
        $customer->notesLog()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }
}
