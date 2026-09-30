<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'business_name', 'logo_path', 'address', 'phone', 'email', 'website',
        'tax_number', 'bank_details', 'invoice_prefix', 'invoice_number_start',
        'next_invoice_sequence', 'default_tax_rate', 'default_currency',
        'invoice_terms', 'footer_text', 'signature_image_path', 'stamp_image_path',
        'state_code', 'receipt_width', 'sms_payment_reminders', 'sms_birthday_wishes',
    ];

    protected function casts(): array
    {
        return [
            'bank_details' => 'array',
            'invoice_number_start' => 'integer',
            'next_invoice_sequence' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'sms_payment_reminders' => 'boolean',
            'sms_birthday_wishes' => 'boolean',
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
        return static::query()->withoutGlobalScope(TenantScope::class)->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['business_name' => Tenant::query()->find($tenantId)?->name ?? 'My Jewellery Store'],
        );
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
}
