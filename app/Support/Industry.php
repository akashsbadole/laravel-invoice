<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * Reads the industry registry (config/industries.php) so the UI and the
 * calculation service can ask an industry-specific question without
 * hard-coding trade knowledge in components.
 *
 * The registry is config, not an enum — adding an industry is a config entry
 * plus a migration, never a code change.
 */
class Industry
{
    public const FALLBACK = 'jewelry';

    /**
     * @return array<string,array{label:string,description:string,uses_metal_rates:bool,uses_weight_fields:bool,uses_stone_fields:bool,document_type:string,pricing_mode:string,rate_types:list<string>,item_fields:list<string>,template_flags:list<string>,charge_types:list<string>}>
     */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $config */
        $config = config('industries', []);

        return $config;
    }

    public static function default(): string
    {
        return self::FALLBACK;
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    /**
     * Resolve a possibly-unknown key to a configured one.
     */
    public static function normalize(?string $key): string
    {
        return self::exists($key) ? $key : self::default();
    }

    /**
     * @return array<string,mixed>
     */
    public static function config(?string $key): array
    {
        $all = self::all();

        return $all[self::normalize($key)];
    }

    public static function label(?string $key): string
    {
        return (string) self::config($key)['label'];
    }

    /**
     * @return list<string>
     */
    public static function rateTypes(?string $key): array
    {
        /** @var list<string> $types */
        $types = self::config($key)['rate_types'];

        return $types;
    }

    /**
     * @return list<string>
     */
    public static function itemFields(?string $key): array
    {
        /** @var list<string> $fields */
        $fields = self::config($key)['item_fields'];

        return $fields;
    }

    /**
     * The industry the current tenant trades in, normalized.
     */
    public static function current(): string
    {
        return self::normalize(BusinessSetting::current()->industry);
    }

    public static function usesMetalRates(?string $key = null): bool
    {
        return (bool) self::config($key ?? self::current())['uses_metal_rates'];
    }

    public static function usesWeightFields(?string $key = null): bool
    {
        return (bool) self::config($key ?? self::current())['uses_weight_fields'];
    }

    public static function usesStoneFields(?string $key = null): bool
    {
        return (bool) self::config($key ?? self::current())['uses_stone_fields'];
    }

    /**
     * The document type this industry's sales invoices should default to.
     */
    public static function defaultDocumentType(?string $key = null): string
    {
        return (string) self::config($key ?? self::current())['document_type'];
    }

    public static function defaultPricingMode(?string $key = null): string
    {
        return (string) self::config($key ?? self::current())['pricing_mode'];
    }

    /**
     * @return list<string>
     */
    public static function templateFlags(?string $key = null): array
    {
        /** @var list<string> $flags */
        $flags = self::config($key ?? self::current())['template_flags'];

        return $flags;
    }
}
