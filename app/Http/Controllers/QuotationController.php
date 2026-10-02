<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quotations\StoreQuotationDraftRequest;
use App\Enums\CatalogStatus;
use App\Models\CatalogItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

/**
 * Catalog-first quotation building.
 *
 * Staff pick products from the catalog, and the selection is handed to the
 * normal quotation form pre-filled. The draft lives in the session only — it
 * is never persisted, so nothing is created until the quotation is saved.
 */
class QuotationController extends Controller
{
    /**
     * The catalog rows offered by the builder dialog.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function products()
    {
        return CatalogItem::query()
            ->where('status', CatalogStatus::Active->value)
            ->orderBy('name')
            ->get([
                'id', 'name', 'brand', 'item_code', 'model_number', 'hsn_code',
                'size_label', 'finish', 'grade', 'specification', 'unit_label',
                'metal_type', 'purity', 'rate_type',
                'default_rate', 'default_net_weight', 'default_gross_weight',
                'default_length', 'default_width', 'default_wastage_percent',
                'attributes',
            ])
            ->map(fn (CatalogItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'item_code' => $item->item_code,
                'rate_type' => $item->rate_type->value,
                'default_rate' => $item->default_rate,
                'unit_label' => $item->unit_label,
                'description' => $item->description,
            ]);
    }

    public function draft(StoreQuotationDraftRequest $request): RedirectResponse
    {
        $selected = collect($request->validated('items'))
            ->keyBy('catalog_item_id');

        $products = CatalogItem::query()
            ->whereIn('id', $selected->keys())
            ->get()
            ->keyBy('id');

        // Preserve the order the staff picked them in.
        $draft = [];

        foreach ($selected as $catalogItemId => $row) {
            $product = $products->get($catalogItemId);

            if (! $product) {
                continue;
            }

            $draft[] = [
                'catalog_item_id' => $product->id,
                'item_name' => $product->name,
                'item_code' => $product->item_code,
                'hsn_code' => $product->hsn_code,
                'description' => $product->description,
                'brand' => $product->brand,
                'model_number' => $product->model_number,
                'size_label' => $product->size_label,
                'finish' => $product->finish,
                'grade' => $product->grade,
                'specification' => $product->specification,
                'metal_type' => $product->metal_type,
                'purity' => $product->purity,
                'attributes' => $product->attributes ?? [],
                'quantity' => (int) $row['quantity'],
                'rate_type' => $product->rate_type->value,
                'rate' => (float) ($row['rate'] ?? $product->default_rate),
                'net_weight' => (float) $product->default_net_weight,
                'gross_weight' => (float) $product->default_gross_weight,
                'length' => $product->default_length === null ? null : (float) $product->default_length,
                'width' => $product->default_width === null ? null : (float) $product->default_width,
                'wastage_percent' => $product->default_wastage_percent === null ? null : (float) $product->default_wastage_percent,
            ];
        }

        if ($draft === []) {
            return back()->withErrors(['items' => 'Pick at least one product from the catalog.']);
        }

        return to_route('invoices.create', ['document_type' => 'quotation'])
            ->with('quotation_draft', $draft);
    }
}
