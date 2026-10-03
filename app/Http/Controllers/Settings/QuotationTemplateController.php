<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\QuotationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()->tenant_id;

        $templates = QuotationTemplate::query()
            ->forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_active', 'created_at']);

        return Inertia::render('settings/quotation-templates', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'charges' => ['nullable', 'array'],
        ]);

        QuotationTemplate::create([
            ...$validated,
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Template created.']);
    }

    public function update(Request $request, QuotationTemplate $quotationTemplate): RedirectResponse
    {
        $this->authorize('update', $quotationTemplate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'charges' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $quotationTemplate->update($validated);

        return back()->with('toast', ['type' => 'success', 'message' => 'Template updated.']);
    }

    public function destroy(Request $request, QuotationTemplate $quotationTemplate): RedirectResponse
    {
        $this->authorize('delete', $quotationTemplate);

        $quotationTemplate->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Template deleted.']);
    }
}
