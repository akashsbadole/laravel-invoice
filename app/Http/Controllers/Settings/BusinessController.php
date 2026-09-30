<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessSettingsRequest;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function edit(): Response
    {
        $settings = BusinessSetting::current();

        return Inertia::render('settings/business', [
            'settings' => [
                ...$settings->only([
                    'id', 'business_name', 'address', 'phone', 'email', 'website',
                    'tax_number', 'invoice_prefix', 'invoice_number_start',
                    'default_tax_rate', 'default_currency', 'invoice_terms', 'footer_text',
                    'state_code', 'receipt_width', 'sms_payment_reminders', 'sms_birthday_wishes',
                ]),
                'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
                'signature_url' => $settings->signature_image_path ? Storage::disk('public')->url($settings->signature_image_path) : null,
                'stamp_url' => $settings->stamp_image_path ? Storage::disk('public')->url($settings->stamp_image_path) : null,
                'bank_name' => $settings->bank_details['bank_name'] ?? null,
                'account_holder_name' => $settings->bank_details['account_holder_name'] ?? null,
                'account_number' => $settings->bank_details['account_number'] ?? null,
                'ifsc_code' => $settings->bank_details['ifsc_code'] ?? null,
                'upi_id' => $settings->bank_details['upi_id'] ?? null,
            ],
        ]);
    }

    public function update(UpdateBusinessSettingsRequest $request): RedirectResponse
    {
        $settings = BusinessSetting::current();
        $data = $request->safe()->except(['logo', 'signature', 'stamp', 'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'upi_id']);

        $data['bank_details'] = array_filter([
            'bank_name' => $request->input('bank_name'),
            'account_holder_name' => $request->input('account_holder_name'),
            'account_number' => $request->input('account_number'),
            'ifsc_code' => $request->input('ifsc_code'),
            'upi_id' => $request->input('upi_id'),
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('business', 'public');
        }

        if ($request->hasFile('signature')) {
            $data['signature_image_path'] = $request->file('signature')->store('business', 'public');
        }

        if ($request->hasFile('stamp')) {
            $data['stamp_image_path'] = $request->file('stamp')->store('business', 'public');
        }

        // Unchecked checkboxes are absent from the request, so set them explicitly.
        $data['sms_payment_reminders'] = $request->boolean('sms_payment_reminders');
        $data['sms_birthday_wishes'] = $request->boolean('sms_birthday_wishes');

        $settings->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business settings updated.')]);

        return to_route('business.edit');
    }
}
