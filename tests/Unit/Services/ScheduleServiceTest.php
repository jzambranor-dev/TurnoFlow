<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ScheduleService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ScheduleServiceTest extends TestCase
{
    // --- toBool() ---

    public function testToBoolWithBooleanTrue(): void
    {
        $this->assertTrue(ScheduleService::toBool(true));
    }

    public function testToBoolWithBooleanFalse(): void
    {
        $this->assertFalse(ScheduleService::toBool(false));
    }

    public function testToBoolWithIntegerOne(): void
    {
        $this->assertTrue(ScheduleService::toBool(1));
    }

    public function testToBoolWithIntegerZero(): void
    {
        $this->assertFalse(ScheduleService::toBool(0));
    }

    public function testToBoolWithStringTrue(): void
    {
        $this->assertTrue(ScheduleService::toBool('true'));
        $this->assertTrue(ScheduleService::toBool('TRUE'));
        $this->assertTrue(ScheduleService::toBool('True'));
    }

    public function testToBoolWithStringYes(): void
    {
        $this->assertTrue(ScheduleService::toBool('yes'));
        $this->assertTrue(ScheduleService::toBool('y'));
        $this->assertTrue(ScheduleService::toBool('on'));
    }

    public function testToBoolWithStringOne(): void
    {
        $this->assertTrue(ScheduleService::toBool('1'));
    }

    public function testToBoolWithStringT(): void
    {
        $this->assertTrue(ScheduleService::toBool('t'));
    }

    public function testToBoolWithFalsyStrings(): void
    {
        $this->assertFalse(ScheduleService::toBool('false'));
        $this->assertFalse(ScheduleService::toBool('no'));
        $this->assertFalse(ScheduleService::toBool('0'));
        $this->assertFalse(ScheduleService::toBool('off'));
        $this->assertFalse(ScheduleService::toBool(''));
    }

    public function testToBoolWithWhitespace(): void
    {
        $this->assertTrue(ScheduleService::toBool(' true '));
        $this->assertTrue(ScheduleService::toBool(' 1 '));
    }

    // --- parseHourFromCell() ---

    public function testParseHourFromCellWithTimeRange(): void
    {
        $this->assertSame(8, ScheduleService::parseHourFromCell('8:00-17:00'));
        $this->assertSame(14, ScheduleService::parseHourFromCell('14:00-22:00'));
    }

    public function testParseHourFromCellWithSingleTime(): void
    {
        $this->assertSame(9, ScheduleService::parseHourFromCell('9:00'));
        $this->assertSame(22, ScheduleService::parseHourFromCell('22:30'));
    }

    public function testParseHourFromCellWithExcelFraction(): void
    {
        // 0.5 = 12:00 (noon)
        $this->assertSame(12, ScheduleService::parseHourFromCell('0.5'));
        // 0.25 = 6:00
        $this->assertSame(6, ScheduleService::parseHourFromCell('0.25'));
        // 0.0 = 0:00
        $this->assertSame(0, ScheduleService::parseHourFromCell('0'));
    }

    public function testParseHourFromCellWithPlainInteger(): void
    {
        $this->assertSame(8, ScheduleService::parseHourFromCell('8'));
        $this->assertSame(23, ScheduleService::parseHourFromCell('23'));
    }

    public function testParseHourFromCellWithInvalidValue(): void
    {
        $this->assertNull(ScheduleService::parseHourFromCell('abc'));
        $this->assertNull(ScheduleService::parseHourFromCell(''));
    }

    public function testParseHourFromCellWithSpaces(): void
    {
        $this->assertSame(8, ScheduleService::parseHourFromCell(' 8 : 00 - 17:00'));
    }

    public function testParseHourFromCellOutOfRange(): void
    {
        // 25 is > 23 and not a fraction, returns null
        $this->assertNull(ScheduleService::parseHourFromCell('25'));
    }

    // --- normalizeRequiredAdvisors() ---

    public function testNormalizeRequiredAdvisorsWithInteger(): void
    {
        $this->assertSame(5, ScheduleService::normalizeRequiredAdvisors(5));
    }

    public function testNormalizeRequiredAdvisorsWithFloat(): void
    {
        $this->assertSame(3, ScheduleService::normalizeRequiredAdvisors(2.6));
        $this->assertSame(2, ScheduleService::normalizeRequiredAdvisors(2.4));
    }

    public function testNormalizeRequiredAdvisorsWithNull(): void
    {
        $this->assertSame(0, ScheduleService::normalizeRequiredAdvisors(null));
    }

    public function testNormalizeRequiredAdvisorsWithEmptyString(): void
    {
        $this->assertSame(0, ScheduleService::normalizeRequiredAdvisors(''));
    }

    public function testNormalizeRequiredAdvisorsWithStringNumber(): void
    {
        $this->assertSame(10, ScheduleService::normalizeRequiredAdvisors('10'));
    }

    public function testNormalizeRequiredAdvisorsWithCommaDecimal(): void
    {
        $this->assertSame(4, ScheduleService::normalizeRequiredAdvisors('3,7'));
    }

    public function testNormalizeRequiredAdvisorsWithNegative(): void
    {
        $this->assertSame(0, ScheduleService::normalizeRequiredAdvisors(-5));
    }

    public function testNormalizeRequiredAdvisorsWithNonNumericThrows(): void
    {
        $this->expectException(RuntimeException::class);
        ScheduleService::normalizeRequiredAdvisors('abc');
    }

    public function testNormalizeRequiredAdvisorsWithSpacedString(): void
    {
        $this->assertSame(7, ScheduleService::normalizeRequiredAdvisors(' 7 '));
    }
}
