<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Bill Smith Jewellers',
            'industry' => 'jewelry',
            'unit_label' => 'g',
            'invoice_prefix' => 'INV-',
            'invoice_number_start' => 1,
            'default_tax_rate' => 3,
            'default_currency' => 'INR',
            'receipt_width' => 80,
            'state_code' => '27',
            'sms_driver' => 'log',
            'sms_country_code' => '+91',
            'quotation_prefix' => 'QT-',
            'next_quotation_sequence' => 1,
            'challan_prefix' => 'CH-',
            'next_challan_sequence' => 1,
            'sms_payment_reminders' => true,
            'email_payment_reminders' => true,
            'sms_birthday_wishes' => true,
            'sms_anniversary_wishes' => false,
            'quotation_customer_decisions' => true,
            'quotation_show_updates' => true,
            'quotation_alerts_owner' => true,
            'quotation_alerts_email' => false,
        ], $overrides);
    }

    public function test_the_email_payment_reminder_toggle_persists(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('business.update'), $this->payload(['email_payment_reminders' => true]))
            ->assertRedirect();

        $this->assertTrue(BusinessSetting::forTenant($user->tenant_id)->email_payment_reminders);

        // Unchecked checkboxes are simply absent, so the false path matters.
        $payload = $this->payload(['sms_payment_reminders' => true]);
        unset($payload['email_payment_reminders']);

        $this->actingAs($user)
            ->post(route('business.update'), $payload)
            ->assertRedirect();

        $this->assertFalse(BusinessSetting::forTenant($user->tenant_id)->email_payment_reminders);
        $this->assertTrue(BusinessSetting::forTenant($user->tenant_id)->sms_payment_reminders);
    }

    /**
     * Unchecked checkboxes are absent from the payload, so every boolean toggle
     * must resolve to false — not silently keep its previous value.
     */
    public function test_any_toggle_can_be_switched_back_off(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('business.update'), $this->payload())
            ->assertRedirect();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertTrue($settings->sms_birthday_wishes);
        $this->assertTrue($settings->quotation_customer_decisions);

        $payload = $this->payload(['email_payment_reminders' => true]);
        unset($payload['sms_birthday_wishes'], $payload['quotation_customer_decisions']);

        $this->actingAs($user)->post(route('business.update'), $payload)->assertRedirect();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertFalse($settings->sms_birthday_wishes);
        $this->assertFalse($settings->quotation_customer_decisions);
        // Untouched toggles keep their value.
        $this->assertTrue($settings->sms_payment_reminders);
        $this->assertTrue($settings->email_payment_reminders);
    }

    public function test_the_settings_page_exposes_the_toggle(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('business.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/business')
                ->has('settings.email_payment_reminders')
            );
    }

    /**
     * The quotation alerts are the shop's only signal that a customer opened,
     * accepted or declined a quote, so the toggle has to survive a round trip —
     * a checkbox that renders but never saves would be worse than no checkbox.
     */
    public function test_the_quotation_alert_toggles_persist_and_switch_off(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('business.update'), $this->payload([
                'quotation_alerts_owner' => true,
                'quotation_alerts_email' => true,
            ]))
            ->assertRedirect();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertTrue($settings->quotation_alerts_owner);
        $this->assertTrue($settings->quotation_alerts_email);

        // Unchecked checkboxes are absent from the payload entirely.
        $payload = $this->payload();
        unset($payload['quotation_alerts_owner'], $payload['quotation_alerts_email']);

        $this->actingAs($user)->post(route('business.update'), $payload)->assertRedirect();

        $settings = BusinessSetting::forTenant($user->tenant_id);
        $this->assertFalse($settings->quotation_alerts_owner);
        $this->assertFalse($settings->quotation_alerts_email);
    }
}
