<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreMetalRateRequest;
use App\Models\MetalRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MetalRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/metal-rates', [
            'latest' => MetalRate::latestRates(),
            'history' => MetalRate::query()->orderByDesc('rate_date')->orderByDesc('id')->paginate(15),
        ]);
    }

    /**
     * One rate per metal + purity + day: saving again the same day updates it.
     */
    public function store(StoreMetalRateRequest $request): RedirectResponse
    {
        MetalRate::updateOrCreate(
            [
                'metal_type' => ucfirst(strtolower(trim($request->validated('metal_type')))),
                'purity' => strtoupper(trim($request->validated('purity'))),
                'rate_date' => $request->validated('rate_date'),
            ],
            [
                'rate_per_gram' => $request->validated('rate_per_gram'),
                'created_by' => $request->user()->id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate saved.')]);

        return back();
    }

    public function destroy(Request $request, MetalRate $metalRate): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $metalRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rate removed.')]);

        return back();
    }
}
