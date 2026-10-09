<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Eligibility\DateRangePolicy;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;

class DateRangePolicyTest extends TestCase
{
    private function policy(string $from, string $to, string $zone = 'Asia/Shanghai', bool $active = true): DateRangePolicy
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isFlag')->willReturn($active);
        $config->method('getValue')->willReturnCallback(static fn ($key) => [
            'general/from_date' => $from, 'general/to_date' => $to,
        ][$key] ?? '');
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturn($zone);
        return new DateRangePolicy($config, $timezone);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('boundaries')]
    public function testInclusiveStoreDates(string $instant, bool $expected): void
    {
        self::assertSame($expected, $this->policy('2026-10-07', '2026-10-07')->isAllowed(1, new \DateTimeImmutable($instant)));
    }

    public static function boundaries(): array
    {
        return [['2026-10-06T15:59:59Z', false], ['2026-10-06T16:00:00Z', true],
            ['2026-10-07T15:59:59Z', true], ['2026-10-07T16:00:00Z', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedBounds')]
    public function testInvalidDatesFailClosed(string $from, string $to): void
    {
        self::assertFalse($this->policy($from, $to)->isAllowed(1, new \DateTimeImmutable('2026-10-07')));
    }

    public static function malformedBounds(): array
    {
        return [['2026-02-30', ''], ['2026-10-08', '2026-10-07'], ['07/10/2026', ''],
            ['', 'not-a-date'], ['2026-10-07 00:00:00', ''],
            ["2026-10\0-07", ''], ['', "2026-10\0-07"], ["2026-10\n-07", '']];
    }

    public function testMalformedNullByteDateClampsCacheLifetimeInsteadOfThrowing(): void
    {
        self::assertSame(1, $this->policy("2026-10\0-07", '')->clampTtl(1, 3600,
            new \DateTimeImmutable('2026-10-07T00:00:00Z')));
    }

    public function testDisabledDateRuleDoesNotApplyInvalidBounds(): void
    {
        self::assertTrue($this->policy('bad', 'bad', 'UTC', false)->isAllowed(1));
    }

    public function testDateCacheExpiresBeforePeriodStarts(): void
    {
        self::assertSame(60, $this->policy('2026-10-07', '2026-10-07')->clampTtl(1, 3600,
            new \DateTimeImmutable('2026-10-06T15:59:00Z')));
    }

    public function testDateCacheExpiresAtPeriodEnd(): void
    {
        self::assertSame(1, $this->policy('2026-10-07', '2026-10-07')->clampTtl(1, 3600,
            new \DateTimeImmutable('2026-10-07T15:59:59Z')));
    }

    public function testDaylightSavingUsesCalendarDayInsteadOf86400Seconds(): void
    {
        $policy = $this->policy('2026-11-01', '2026-11-01', 'America/New_York');
        self::assertTrue($policy->isAllowed(1, new \DateTimeImmutable('2026-11-02T04:59:59Z')));
        self::assertFalse($policy->isAllowed(1, new \DateTimeImmutable('2026-11-02T05:00:00Z')));
    }
}
