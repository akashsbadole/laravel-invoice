<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogItemController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('settings/catalog', [
            'items' => CatalogItem::query()
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreCatalogItemRequest $request): RedirectResponse
    {
        CatalogItem::create([
            ...$request->validated(),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item added.')]);

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
