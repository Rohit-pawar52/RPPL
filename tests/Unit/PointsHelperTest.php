<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * points(): auction points as a whole number with Indian (lakh) grouping,
 * never a currency symbol.
 */
class PointsHelperTest extends TestCase
{
    public function test_points_use_indian_grouping_without_a_currency_symbol(): void
    {
        $this->assertSame('0', points(0));
        $this->assertSame('500', points(500));
        $this->assertSame('999', points(999));
        $this->assertSame('1,000', points(1000));
        $this->assertSame('12,345', points(12345));
        $this->assertSame('1,23,456', points(123456));
        $this->assertSame('6,00,000', points(600000));
        $this->assertSame('15,00,000', points(1500000));
        $this->assertSame('1,23,45,678', points(12345678));
    }

    public function test_points_round_to_a_whole_number_and_can_carry_the_unit(): void
    {
        $this->assertSame('4,001', points(4000.5));
        $this->assertSame('4,000', points('4000.00'));
        $this->assertSame('0', points(null));
        $this->assertSame('-2,500', points(-2500));
        $this->assertSame('6,00,000 pts', points(600000, true));
    }
}
