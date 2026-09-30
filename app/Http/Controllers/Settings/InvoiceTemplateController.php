<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreInvoiceTemplateRequest;
use App\Http\Requests\Settings\UpdateInvoiceTemplateRequest;
use App\Models\InvoiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceTemplateController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);

        return Inertia::render('settings/invoice-templates', [
            'templates' => InvoiceTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInvoiceTemplateRequest $request): RedirectResponse
    {
        InvoiceTemplate::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'is_default' => false,
            'layout_config' => $this->layoutConfigFromRequest($request),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template created.')]);

        return back();
    }

    public function update(UpdateInvoiceTemplateRequest $request, InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        $invoiceTemplate->update([
            'name' => $request->validated('name'),
            'layout_config' => $this->layoutConfigFromRequest($request),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template updated.')]);

        return back();
    }

    public function setDefault(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);

        DB::transaction(function () use ($invoiceTemplate) {
            InvoiceTemplate::query()->where('id', '!=', $invoiceTemplate->id)->update(['is_default' => false]);
            $invoiceTemplate->update(['is_default' => true]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Default template updated.')]);

        return back();
    }

    public function destroy(InvoiceTemplate $invoiceTemplate): RedirectResponse
    {
        abort_unless(request()->user()->role->canManageSettings(), 403);
        abort_if($invoiceTemplate->is_default, 422, 'Set another template as default before deleting this one.');

        $invoiceTemplate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template removed.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function layoutConfigFromRequest(StoreInvoiceTemplateRequest|UpdateInvoiceTemplateRequest $request): array
    {
        return [
            'accent_color' => $request->validated('accent_color'),
            'header_alignment' => $request->validated('header_alignment'),
            'footer_note' => $request->validated('footer_note'),
            'show_huid' => $request->boolean('show_huid'),
            'show_hsn' => $request->boolean('show_hsn'),
            'show_stone_details' => $request->boolean('show_stone_details'),
            'show_bank_details' => $request->boolean('show_bank_details'),
            'show_signature' => $request->boolean('show_signature'),
            'show_stamp' => $request->boolean('show_stamp'),
            'show_qr_code' => $request->boolean('show_qr_code'),
        ];
    }
}
