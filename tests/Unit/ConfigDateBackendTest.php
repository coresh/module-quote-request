<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config\Backend\Date;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercises module validation only; native configuration persistence is not run. */
class ConfigDateBackendTest extends TestCase
{
    private function backend(): Date
    {
        return $this->getMockBuilder(Date::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    #[DataProvider('validDates')]
    public function testValidDatesAndEmptyBoundsAreNormalized(mixed $value, string $expected): void
    {
        self::assertSame($expected, $this->backend()->normalize($value));
    }

    public static function validDates(): array
    {
        return [[null, ''], ['', ''], ['  ', ''], ['2026-10-09', '2026-10-09'],
            [' 2026-10-09 ', '2026-10-09'], ['2024-02-29', '2024-02-29']];
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDatesProduceAValidationErrorInsteadOfAPhpParserError(mixed $value): void
    {
        $this->expectException(LocalizedException::class);
        $this->backend()->normalize($value);
    }

    public static function invalidDates(): array
    {
        return [[[]], [new \stdClass()], ['2026-02-29'], ['2026-13-01'], ['2026-00-09'],
            ['2026-1-09'], ['2026-10-9'], ['09/10/2026'], ['2026-10-09T00:00:00Z'],
            ["2026-10\0-09"], ["2026-10\n-09"], ["2026-10\xFF-09"]];
    }

    public function testBeforeSaveRejectsAReversedNonemptyPeriod(): void
    {
        $backend = $this->backend();
        $backend->setData(['value' => '2026-10-08', 'fieldset_data' => [
            'from_date' => '2026-10-09', 'to_date' => '2026-10-08'
        ]]);
        $this->expectException(LocalizedException::class);
        $backend->beforeSave();
    }
}
