<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessSettingsRequest;
use App\Models\BusinessSetting;
use App\Models\ChargeType;
use App\Support\Industry;
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
            'industries' => collect(Industry::all())
                ->map(fn (array $config, string $key) => [
                    'key' => $key,
                    'label' => $config['label'],
                    'description' => $config['description'],
                ])
                ->values()
                ->all(),
            'settings' => [
                ...$settings->only([
                    'id', 'business_name', 'address', 'pincode', 'phone', 'email', 'website',
                    'tax_number', 'invoice_prefix', 'invoice_number_start', 'quotation_prefix', 'challan_prefix',
                    'sms_driver', 'sms_country_code',
                    'sms_twilio_from', 'sms_http_url', 'sms_http_to_field', 'sms_http_message_field',
                    'default_tax_rate', 'default_currency', 'invoice_terms', 'footer_text',
                    'state_code', 'receipt_width', 'sms_payment_reminders', 'sms_birthday_wishes', 'sms_anniversary_wishes',
                    'quotation_customer_decisions', 'quotation_show_updates',
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
        $data = $request->safe()->except(['logo', 'signature', 'stamp', 'bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'upi_id', 'sms_twilio_sid', 'sms_twilio_token', 'sms_http_token']);

        // Secrets are write-only: blank means "keep the stored value".
        foreach (['sms_twilio_sid', 'sms_twilio_token', 'sms_http_token'] as $secret) {
            if ($request->filled($secret)) {
                $data[$secret] = $request->input($secret);
            }
        }

        $data['bank_details'] = array_filter([
            'bank_name' => $request->input('bank_name'),
            'account_holder_name' => $request->input('account_holder_name'),
            'account_number' => $request->input('account_number'),
            'ifsc_code' => $request->input('ifsc_code'),
            'upi_id' => $request->input('upi_id'),
        ]);

        // Switching trade re-seeds the charge catalogue so a tiles shop is not
        // offered "Hallmarking Charge" and a jeweller is not offered "Wastage
        // %" twice. Only system defaults are touched; custom types stay.
        if ($request->validated('industry') !== $settings->industry) {
            $this->syncIndustryCharges($settings, $request->validated('industry'));
        }

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
        $data['sms_anniversary_wishes'] = $request->boolean('sms_anniversary_wishes');
        $data['quotation_customer_decisions'] = $request->boolean('quotation_customer_decisions');
        $data['quotation_show_updates'] = $request->boolean('quotation_show_updates');

        $settings->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Business settings updated.')]);

        return to_route('business.edit');
    }

    /**
     * Retire system charge types the new industry has no use for and create
     * the ones it does. Tenant-authored charge types are never touched.
     *
     * @param  list<string>  $industryChargeNames
     */
    protected function syncIndustryCharges(BusinessSetting $settings, string $industry): void
    {
        $config = Industry::config($industry);
        $names = $config['charge_types'];

        ChargeType::query()
            ->where('is_system', true)
            ->whereNotIn('name', $names)
            ->update(['is_active' => false]);

        $existing = ChargeType::query()
            ->where('is_system', true)
            ->pluck('name')
            ->all();

        $sortOrder = (int) ChargeType::query()->max('sort_order');

        foreach (array_diff($names, $existing) as $name) {
            ChargeType::create([
                'name' => $name,
                'code' => str($name)->slug('_')->substr(0, 20)->value(),
                'calculation_type' => in_array($name, ['Wastage'], true)
                    ? ChargeCalculationType::Percentage
                    : ChargeCalculationType::Fixed,
                'applies_to' => ChargeAppliesTo::Invoice,
                'is_taxable' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => ++$sortOrder,
            ]);
        }
    }
}
