<?php

namespace Tests\Unit;

use App\Support\NumberToWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    /**
     * Unhyphenated compounds ("thirty four") are the usual form on an Indian
     * tax invoice.
     *
     * @return list<array{0: float, 1: string}>
     */
    public static function amounts(): array
    {
        return [
            [1.00, 'one rupee only'],
            [2.50, 'two rupees and fifty paise only'],
            [100.00, 'one hundred rupees only'],
            [7150.00, 'seven thousand one hundred fifty rupees only'],
            [123456.00, 'one lakh twenty three thousand four hundred fifty six rupees only'],
            [100000.00, 'one lakh rupees only'],
            [1234567.00, 'twelve lakh thirty four thousand five hundred sixty seven rupees only'],
            [10000000.00, 'one crore rupees only'],
            [150000000.00, 'fifteen crore rupees only'],
            [123456789.00, 'twelve crore thirty four lakh fifty six thousand seven hundred eighty nine rupees only'],
            [12345678.89, 'one crore twenty three lakh forty five thousand six hundred seventy eight rupees and eighty nine paise only'],
            [0.05, 'five paise only'],
            [0.00, ''],
            [-1500.00, 'minus one thousand five hundred rupees only'],
        ];
    }

    /**
     * @dataProvider amounts
     */
    #[DataProvider('amounts')]
    public function test_it_writes_indian_grouped_amounts(float $amount, string $expected): void
    {
        $this->assertSame($expected, NumberToWords::rupees($amount));
    }

    public function test_it_groups_by_lakh_and_crore_not_thousand_and_million(): void
    {
        // 10,000 is ten thousand; 100,000 is one lakh. This is the distinction
        // that makes an Indian invoice read correctly.
        $this->assertSame('ten thousand', NumberToWords::integer(10000));
        $this->assertSame('one lakh', NumberToWords::integer(100000));
        $this->assertSame('ten lakh', NumberToWords::integer(1000000));
        $this->assertSame('one crore', NumberToWords::integer(10000000));
    }

    public function test_float_noise_does_not_become_a_paise(): void
    {
        // The kind of value a stored decimal column can hand back.
        $this->assertSame('one thousand two hundred thirty four rupees and fifty six paise only', NumberToWords::rupees(1234.5600000000001));
    }
}
