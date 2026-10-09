<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Security\RateLimiter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/** Tests the actual limiter policy; mocked counters do not establish database concurrency. */
class RateLimiterSecurityTest extends TestCase
{
    private function fixture(bool $enabled = true, string $limit = '5', string $window = '600'): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->with('security/rate_limit_enabled', self::anything())->willReturn($enabled);
        $config->method('getValue')->willReturnCallback(static fn (string $path): string =>
            $path === 'security/rate_limit_max' ? $limit : $window);
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn ($value) => '`' . $value . '`');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('prefix_coresh_quote_request_rate_limit');
        return ['limiter' => new RateLimiter($resource, $config), 'connection' => $connection,
            'resource' => $resource, 'config' => $config];
    }

    public function testDisabledLimiterNeverTouchesPersistence(): void
    {
        $f = $this->fixture(false);
        $f['resource']->expects(self::never())->method('getConnection');
        $f['limiter']->consume(2, '192.0.2.10');
    }

    public function testBoundCounterAllowsLimitAndRejectsNextAttemptAcrossServiceInstances(): void
    {
        $f = $this->fixture();
        $counters = [];
        $f['connection']->expects(self::exactly(6))->method('query')->willReturnCallback(
            static function ($sql, array $bind) use (&$counters): void {
                self::assertStringContainsString('ON DUPLICATE KEY UPDATE hits = hits + 1', $sql);
                self::assertStringNotContainsString('192.0.2.10', $sql);
                self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $bind[0]);
                self::assertIsInt($bind[1]);
                $counters[$bind[0]] = ($counters[$bind[0]] ?? 0) + 1;
            }
        );
        $f['connection']->method('fetchOne')->willReturnCallback(
            static function ($sql, array $bind) use (&$counters): int { return $counters[$bind[0]]; }
        );
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            // The policy has no per-instance counter that can reset by recreating a service.
            (new RateLimiter($f['resource'], $f['config']))->consume(2, '192.0.2.10');
        }
        $this->expectException(LocalizedException::class);
        $f['limiter']->consume(2, '192.0.2.10');
    }

    public function testStoreAndAddressIsolationAndMalformedAddressShareFallbackBucket(): void
    {
        $f = $this->fixture(window: '86400');
        $keys = [];
        $f['connection']->method('query')->willReturnCallback(
            static function ($sql, array $bind) use (&$keys): void { $keys[] = $bind[0]; }
        );
        $f['connection']->method('fetchOne')->willReturn(1);
        $f['limiter']->consume(1, '192.0.2.10');
        $f['limiter']->consume(2, '192.0.2.10');
        $f['limiter']->consume(1, '192.0.2.11');
        $f['limiter']->consume(1, "' OR 1=1 --");
        $f['limiter']->consume(1, 'invalid-address');
        self::assertCount(4, array_unique($keys));
        self::assertSame($keys[3], $keys[4]);
    }

    public function testDatabaseFailureFailsClosed(): void
    {
        $f = $this->fixture();
        $f['connection']->method('query')->willThrowException(new \RuntimeException('Database unavailable'));
        $f['connection']->expects(self::never())->method('fetchOne');
        $this->expectException(\RuntimeException::class);
        $f['limiter']->consume(1, '192.0.2.10');
    }
}
