<?php

namespace App\Http\Controllers;

use App\Enums\CatalogStatus;
use App\Enums\Permission;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Models\BusinessSetting;
use App\Models\CatalogItem;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use App\Support\CatalogField;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The product catalog: what the tenant sells, at what price. Quotations and
 * invoices are built from these rows, so this is a working surface rather
 * than a settings screen.
 */
class CatalogItemController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $industry = BusinessSetting::current()->industryKey();
        $status = $request->string('status')->toString();

        return Inertia::render('catalog/index', [
            'items' => CatalogItem::query()
                ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%"))
                ->when($status === 'active', fn ($q) => $q->where('status', CatalogStatus::Active->value))
                ->when($status === 'draft', fn ($q) => $q->where('status', CatalogStatus::Draft->value))
                ->when($status === 'inactive', fn ($q) => $q->where('status', CatalogStatus::Inactive->value))
                ->when($status === 'discontinued', fn ($q) => $q->where('status', CatalogStatus::Discontinued->value))
                ->when($status === 'low_stock', fn ($q) => $q->where('stock_tracked', true)
                    ->whereColumn('stock_quantity', '<=', 'reorder_level')
                    ->where('reorder_level', '>', 0))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (CatalogItem $item) => $this->presentItem($item)),
            'filters' => ['search' => $search, 'status' => $status],
            'industry' => $this->industryProps($industry),
            'fields' => $this->fieldProps($industry),
            'lowStockCount' => CatalogItem::query()
                ->where('stock_tracked', true)
                ->whereColumn('stock_quantity', '<=', 'reorder_level')
                ->where('reorder_level', '>', 0)
                ->count(),
            'draftCount' => CatalogItem::query()
                ->where('status', CatalogStatus::Draft->value)
                ->count(),
        ]);
    }

    /**
     * The fields the form should render, straight from the registry.
     *
     * @return list<array{name:string,label:string,type:string,group:string,hint?:string}>
     */
    protected function fieldProps(string $industry): array
    {
        return CatalogField::allForIndustry($industry);
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentItem(CatalogItem $item): array
    {
        return [
            ...$item->only([
                'id', 'name', 'item_code', 'barcode', 'brand', 'model_number',
                'hsn_code', 'rate_type', 'default_rate', 'cost_price',
                'stock_tracked', 'stock_quantity', 'reorder_level', 'stock_unit',
                'image_path', 'status',
            ]),
            'rate_type' => $item->rate_type?->value,
            'image_url' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
            'is_low_stock' => $item->isLowOnStock(),
            'is_active' => $item->isActive(),
        ];
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
            'show_all_fields' => CatalogField::showAll(),
        ];
    }

    public function store(StoreCatalogItemRequest $request): RedirectResponse
    {
        $payload = $request->catalogPayload();
        $payload['image_path'] = $this->storeImage($request);
        $payload['created_by'] = $request->user()->id;

        // New products default to active unless explicitly saved as a draft.
        $payload['status'] = $payload['status'] ?? CatalogStatus::Active->value;

        // Opening stock has to be recorded, not just stored, or the ledger
        // cannot explain the balance later.
        $opening = (float) ($payload['stock_quantity'] ?? 0);
        $payload['stock_quantity'] = 0;

        $item = CatalogItem::create($payload);

        if ($request->boolean('stock_tracked') && $opening !== 0.0) {
            $this->inventory->setOpeningStock($item, $opening, $request->user()->id);
        }

        // Auto-activate: a draft that now has stock is clearly ready to sell.
        if ($item->isDraft() && $opening !== 0.0) {
            $item->update(['status' => CatalogStatus::Active]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product added to the catalog.')]);

        return back();
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem): RedirectResponse
    {
        $payload = $request->catalogPayload();

        if ($request->hasFile('image')) {
            $this->deleteImage($catalogItem->image_path);
            $payload['image_path'] = $this->storeImage($request);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($catalogItem->image_path);
            $payload['image_path'] = null;
        }

        // Stock is only ever changed through the movement ledger, never by
        // editing the number in the form.
        $submitted = (float) $request->validated('stock_quantity', $catalogItem->stock_quantity);
        $payload['stock_quantity'] = $catalogItem->stock_quantity;

        $catalogItem->update($payload);

        if ($request->boolean('stock_tracked') && $submitted !== (float) $catalogItem->stock_quantity) {
            $this->inventory->setOpeningStock($catalogItem, $submitted, $request->user()->id);
        }

        // Auto-activate: a draft that now has stock is ready to sell.
        if ($catalogItem->isDraft() && $submitted !== 0.0) {
            $catalogItem->update(['status' => CatalogStatus::Active]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item updated.')]);

        return back();
    }

    /**
     * Bring a product back on sale: drafts, inactive and discontinued items
     * all activate here so the list only needs one button.
     */
    public function activate(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        if ($catalogItem->status === CatalogStatus::Active) {
            return back()->withErrors(['status' => __('This product is already active.')]);
        }

        $catalogItem->update(['status' => CatalogStatus::Active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item activated.')]);

        return back();
    }

    /**
     * Bring several off-sale products back on sale in one go.
     */
    public function activateSelected(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageCatalog), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:catalog_items,id'],
        ]);

        $count = CatalogItem::query()
            ->whereIn('id', $validated['ids'])
            ->where('status', '!=', CatalogStatus::Active->value)
            ->update(['status' => CatalogStatus::Active->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Activated {$count} product(s).")]);

        return back();
    }

    /**
     * Record a stock movement against a catalog item.
     */
    public function adjustStock(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        abort_unless($request->user()->canDo(Permission::ManageInventory), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', [
                InventoryMovement::TYPE_IN,
                InventoryMovement::TYPE_OUT,
                InventoryMovement::TYPE_ADJUSTMENT,
            ])],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($catalogItem->stock_tracked, 422, 'Stock tracking is off for this product.');

        $this->inventory->move(
            $catalogItem,
            $validated['type'],
            (float) $validated['quantity'],
            $request->user()->id,
            ['reason' => $validated['reason'] ?? null, 'note' => $validated['note'] ?? null],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated.')]);

        return back();
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        abort_unless(request()->user()->canDo(Permission::ManageCatalog), 403);

        $this->deleteImage($catalogItem->image_path);
        $catalogItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Catalog item removed.')]);

        return back();
    }

    protected function storeImage(StoreCatalogItemRequest|UpdateCatalogItemRequest $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('catalog', 'public');
    }

    protected function deleteImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}
