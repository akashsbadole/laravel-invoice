<?php

namespace App\Services;

use App\Enums\ChargeAppliesTo;
use App\Enums\ChargeCalculationType;
use App\Models\BusinessSetting;
use App\Models\ChargeType;
use App\Support\Industry;

/**
 * Seeds a new tenant's charge catalogue from its industry (config/industries.php).
 *
 * The original charge types were inserted by a migration for the default
 * tenant only, so a freshly registered business had none at all — meaning no
 * making/wastage/freight charges could be applied to its invoices.
 */
class ChargeTypeSeeder
{
    /**
     * Item-level charges are expressed as a percentage of the item value;
     * invoice-level ones are flat or percentage of the subtotal.
     */
    private const ITEM_CHARGES = [
        'Making Charge' => 10,
        'Wastage' => 5,
    ];

    public function seedForTenant(int $tenantId, ?string $industry = null): void
    {
        $industry ??= BusinessSetting::forTenant($tenantId)->industryKey();

        $names = $this->chargeNamesFor($industry);
        $existing = ChargeType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_system', true)
            ->pluck('name')
            ->all();

        $sortOrder = (int) ChargeType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->max('sort_order');

        foreach ($names as $name) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $isItemCharge = array_key_exists($name, self::ITEM_CHARGES);

            // tenant_id is not mass-assignable (it must never come from
            // request input), so set it explicitly rather than via create().
            $chargeType = new ChargeType([
                'name' => $name,
                'code' => str($name)->slug('_')->substr(0, 20)->value(),
                'calculation_type' => $isItemCharge
                    ? ChargeCalculationType::Percentage
                    : ChargeCalculationType::Fixed,
                'applies_to' => $isItemCharge ? ChargeAppliesTo::Item : ChargeAppliesTo::Invoice,
                'default_rate' => $isItemCharge ? self::ITEM_CHARGES[$name] : 0,
                'is_taxable' => false,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => ++$sortOrder,
            ]);
            $chargeType->tenant_id = $tenantId;
            $chargeType->save();
        }
    }

    /**
     * @return list<string>
     */
    protected function chargeNamesFor(string $industry): array
    {
        /** @var list<string> $names */
        $names = Industry::config($industry)['charge_types'];

        return $names;
    }
}
