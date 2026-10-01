<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The product catalog: what the tenant sells, at what price. Quotations and
 * invoices are built from these rows, so this is a working surface rather
 * than a settings screen.
 */
class CatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $industry = BusinessSetting::current()->industryKey();

        return Inertia::render('catalog/index', [
            'items' => CatalogItem::query()
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['search' => $search],
            'industry' => $this->industryProps($industry),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function industryProps(string $industry): array
    {
        return [
            'key' => $industry,
            'label' => Industry::label($industry),
            'description' => (string) Industry::config($industry)['description'],
            'uses_metal_rates' => Industry::usesMetalRates($industry),
            'uses_weight_fields' => Industry::usesWeightFields($industry),
            'uses_stone_fields' => Industry::usesStoneFields($industry),
            'document_type' => Industry::defaultDocumentType($industry),
            'pricing_mode' => Industry::defaultPricingMode($industry),
            'rate_types' => Industry::rateTypes($industry),
            'item_fields' => Industry::itemFields($industry),
            'template_flags' => Industry::templateFlags($industry),
            'charge_types' => Industry::config($industry)['charge_types'],
        ];
    }

    public function store(StoreCatalogItemRequest $request): RedirectResponse
    {
        CatalogItem::create([
            ...$request->validated(),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added to the catalog.')]);

        return back();
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item updated.')]);

        return back();
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        abort_unless(request()->user()->role->canWrite(), 403);

        $catalogItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item removed.')]);

        return back();
    }
}
