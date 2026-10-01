<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\InvoiceShareLink;
use App\Models\InvoiceTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicInvoiceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $shareLink = InvoiceShareLink::query()->where('token', $token)->first();

        if (! $shareLink || ! $shareLink->isUsable()) {
            return Inertia::render('invoices/public', [
                'status' => 'unavailable',
            ]);
        }

        if ($shareLink->password_hash && ! $request->session()->get("invoice_share_verified.{$token}")) {
            return Inertia::render('invoices/public', [
                'status' => 'password_required',
                'token' => $token,
            ]);
        }

        $shareLink->markViewed();

        $invoice = $shareLink->invoice()->with([
            'customer', 'salesperson', 'items.charges', 'payments', 'template',
        ])->firstOrFail();

        $template = $invoice->template ?? InvoiceTemplate::forTenantDefault($invoice->tenant_id);
        $settings = BusinessSetting::forTenant($invoice->tenant_id);

        return Inertia::render('invoices/public', [
            'status' => 'ok',
            'token' => $token,
            'invoice' => $invoice,
            'template' => $template->layout_config + InvoiceTemplate::defaultLayoutConfig(),
            'business' => [
                ...$settings->only([
                    'business_name', 'logo_path', 'address', 'phone', 'email',
                    'website', 'tax_number', 'footer_text',
                ]),
                'upi_id' => $settings->bank_details['upi_id'] ?? null,
            ],
        ]);
    }

    public function verifyPassword(Request $request, string $token): RedirectResponse
    {
        $shareLink = InvoiceShareLink::query()->where('token', $token)->firstOrFail();

        $request->validate(['password' => ['required', 'string']]);

        if (! $shareLink->isUsable() || ! $shareLink->checkPassword($request->string('password'))) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put("invoice_share_verified.{$token}", true);

        return to_route('invoices.public.show', $token);
    }
}
