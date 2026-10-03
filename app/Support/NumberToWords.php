<?php

namespace App\Support;

/**
 * Indian-format amount to words, for the "Total in words" line a GST tax
 * invoice is legally expected to carry.
 *
 * Indian grouping is lakh/crore rather than million/billion, and paise are
 * written as a fraction ("and fifty paise only"), not as whole rupees.
 */
final class NumberToWords
{
    /** @var list<string> */
    private const ONES = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen',
        'eighteen', 'nineteen',
    ];

    /** @var list<string> */
    private const TENS = [
        '', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety',
    ];

    /**
     * "one lakh twenty-three thousand four hundred fifty-six rupees and
     * seventy paise only".
     *
     * Returns an empty string for zero rather than saying "zero rupees",
     * which reads badly on a document line.
     */
    public static function rupees(float $amount): string
    {
        $negative = $amount < 0;
        $amount = abs($amount);

        // Round to paise first: a stored total can carry float noise like
        // 1234.5600000000001, which must not become "one paise".
        $paise = (int) round(fmod(round($amount, 2), 1.0) * 100);
        $rupees = (int) round($amount - $paise / 100);

        if ($rupees === 0 && $paise === 0) {
            return '';
        }

        // Sub-paise-only amounts read "five paise only", not
        // "zero rupees and five paise only".
        if ($rupees === 0) {
            return ($negative ? 'minus ' : '').self::integer($paise).($paise === 1 ? ' paise only' : ' paise only');
        }

        $words = self::integer($rupees);

        $words .= $rupees === 1 ? ' rupee' : ' rupees';

        if ($paise > 0) {
            $words .= ' and '.self::integer($paise).' paise';
        }

        return ($negative ? 'minus ' : '').$words.' only';
    }

    /**
     * Indian grouping: crore, lakh, thousand, hundred.
     */
    public static function integer(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $parts = [];

        $number = self::push($number, 10_000_000, 'crore', $parts);
        $number = self::push($number, 100_000, 'lakh', $parts);
        $number = self::push($number, 1_000, 'thousand', $parts);
        $number = self::push($number, 100, 'hundred', $parts);

        if ($number > 0) {
            $parts[] = self::underHundred($number);
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Subtract one Indian group from the number and record its name.
     *
     * @param  list<string>  $parts
     */
    private static function push(int $number, int $divisor, string $name, array &$parts): int
    {
        if ($number < $divisor) {
            return $number;
        }

        $count = intdiv($number, $divisor);
        $parts[] = self::integer($count).' '.$name;

        return $number % $divisor;
    }

    private static function underHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $tens = intdiv($number, 10);
        $ones = $number % 10;

        return trim(self::TENS[$tens].' '.self::ONES[$ones]);
    }
}
