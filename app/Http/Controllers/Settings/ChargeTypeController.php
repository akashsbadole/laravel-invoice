<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreChargeTypeRequest;
use App\Http\Requests\Settings\UpdateChargeTypeRequest;
use App\Models\ChargeType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChargeTypeController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);

        return Inertia::render('settings/charge-types', [
            'chargeTypes' => ChargeType::query()->orderBy('applies_to')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreChargeTypeRequest $request): RedirectResponse
    {
        ChargeType::create([
            ...$request->validated(),
            'is_taxable' => $request->boolean('is_taxable'),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => ChargeType::query()->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Charge type added.')]);

        return back();
    }

    public function update(UpdateChargeTypeRequest $request, ChargeType $chargeType): RedirectResponse
    {
        $chargeType->update([
            ...$request->validated(),
            'is_taxable' => $request->boolean('is_taxable'),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Charge type updated.')]);

        return back();
    }

    public function destroy(ChargeType $chargeType): RedirectResponse
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);
        abort_unless($chargeType->isDeletable(), 422, 'Built-in charge types can be deactivated but not deleted.');

        $chargeType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Charge type removed.')]);

        return back();
    }
}
