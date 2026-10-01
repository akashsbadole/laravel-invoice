<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * Resolves the theme a tenant's thermal receipts should print with.
 *
 * Centralised because the receipt is rendered as raw CSS for the browser
 * *and* for DomPDF: an unvalidated colour would reach a style attribute, so it
 * is clamped to a safe hex here rather than trusted from the form.
 */
final class ReceiptTheme
{
    public const DEFAULT_ACCENT = '#0F172A';

    /**
     * @return array{
     *     accent:string,
     *     showLogo:bool,
     *     showSignature:bool,
     *     showStamp:bool,
     *     showGstin:bool,
     *     footer:?string
     * }
     */
    public static function for(BusinessSetting $settings): array
    {
        return [
            'accent' => self::sanitize($settings->receipt_accent_color),
            'showLogo' => (bool) $settings->receipt_show_logo,
            'showSignature' => (bool) $settings->receipt_show_signature,
            'showStamp' => (bool) $settings->receipt_show_stamp,
            'showGstin' => (bool) $settings->receipt_show_gstin,
            'footer' => $settings->receipt_footer ?: null,
        ];
    }

    /**
     * Accept only #rgb / #rrggbb, so a malformed value cannot inject CSS.
     */
    public static function sanitize(?string $color): string
    {
        $color = strtoupper(trim((string) $color));

        if (preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', $color)) {
            // Expand #abc to #aabbcc so it is valid in both CSS engines.
            if (strlen($color) === 4) {
                return '#'.str_repeat($color[1], 2).str_repeat($color[2], 2).str_repeat($color[3], 2);
            }

            return $color;
        }

        return self::DEFAULT_ACCENT;
    }
}
