<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\TenantScope;
use App\Support\Industry;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'business_name', 'industry', 'unit_label', 'logo_path', 'address', 'pincode', 'phone', 'email', 'website',
        'tax_number', 'bank_details', 'invoice_prefix', 'invoice_number_start',
        'quotation_prefix', 'next_quotation_sequence',
        'challan_prefix', 'next_challan_sequence',
        'next_invoice_sequence', 'default_tax_rate', 'default_currency',
        'invoice_terms', 'footer_text', 'signature_image_path', 'stamp_image_path',
        'state_code', 'receipt_width', 'sms_payment_reminders', 'sms_birthday_wishes', 'sms_anniversary_wishes',
        'quotation_customer_decisions', 'quotation_show_updates',
        'sms_driver', 'sms_country_code',
        'sms_twilio_sid', 'sms_twilio_token', 'sms_twilio_from',
        'sms_http_url', 'sms_http_token', 'sms_http_to_field', 'sms_http_message_field',
    ];

    protected function casts(): array
    {
        return [
            'bank_details' => 'array',
            'invoice_number_start' => 'integer',
            'next_invoice_sequence' => 'integer',
            'next_quotation_sequence' => 'integer',
            'next_challan_sequence' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'sms_payment_reminders' => 'boolean',
            'sms_birthday_wishes' => 'boolean',
            'sms_anniversary_wishes' => 'boolean',
            'quotation_customer_decisions' => 'boolean',
            'quotation_show_updates' => 'boolean',
        ];
    }

    /**
     * The settings row is a per-tenant singleton — one row per tenant.
     */
    public static function current(): self
    {
        return static::forTenant(Tenant::currentId() ?? Tenant::current()?->id);
    }

    public static function forTenant(?int $tenantId): self
    {
        $tenant = Tenant::query()->find($tenantId);

        $settings = static::query()->withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'business_name' => $tenant?->name ?? 'My Store',
                'industry' => $tenant?->industry ?? Industry::default(),
            ],
        );

        // Outside a request (console commands, queued jobs) the BelongsToTenant
        // hook has no tenant context to read, so stamp it explicitly.
        if ($settings->tenant_id === null && $tenantId !== null) {
            $settings->tenant_id = $tenantId;
            $settings->saveQuietly();
        }

        return $settings;
    }

    /**
     * The configured industry key, normalized to one that actually exists
     * in config/industries.php.
     */
    public function industryKey(): string
    {
        return Industry::normalize($this->industry);
    }

    /**
     * Build the next invoice number (e.g. JWL-2026-00001) and advance
     * the stored sequence counter. Callers should wrap this in a
     * transaction with a row lock to stay safe under concurrency.
     */
    public function nextInvoiceNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $sequence = max($this->next_invoice_sequence, $this->invoice_number_start);
        $number = sprintf('%s-%d-%05d', $this->invoice_prefix, $year, $sequence);

        $this->next_invoice_sequence = $sequence + 1;
        $this->save();

        return $number;
    }

    /**
     * Build the next quotation number (e.g. QT-2026-00001) on its own
     * per-tenant sequence, separate from invoice numbering.
     */
    public function nextQuotationNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $sequence = max($this->next_quotation_sequence ?? 1, 1);
        $number = sprintf('%s-%d-%05d', $this->quotation_prefix ?: 'QT', $year, $sequence);

        $this->next_quotation_sequence = $sequence + 1;
        $this->save();

        return $number;
    }

    /**
     * Build the next delivery-challan number (e.g. DC-2026-00001) on its
     * own per-tenant sequence.
     */
    public function nextChallanNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $sequence = max($this->next_challan_sequence ?? 1, 1);
        $number = sprintf('%s-%d-%05d', $this->challan_prefix ?: 'DC', $year, $sequence);

        $this->next_challan_sequence = $sequence + 1;
        $this->save();

        return $number;
    }
}
