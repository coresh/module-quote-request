<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Migration\MigrationApproval;
use PHPUnit\Framework\TestCase;

class MigrationApprovalTest extends TestCase
{
    public function testApprovalIsBoundToStoreTokenDigestAndTenMinuteWindow(): void
    {
        $approval = new MigrationApproval();
        $created = $approval->create(2, 1000);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $created['token']);
        self::assertSame(hash('sha256', $created['token']), $created['state']['token_hash']);
        self::assertSame(2, $created['state']['store_id']);
        self::assertSame(1000, $created['state']['created_at']);
        self::assertSame(1600, $created['state']['expires_at']);
        self::assertTrue($approval->validate($created['token'], 2, $created['state'], 1000));
        self::assertTrue($approval->validate($created['token'], 2, $created['state'], 1600));
        self::assertFalse($approval->validate($created['token'], 2, $created['state'], 1601));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidApprovals')]
    public function testMalformedMissingWrongOrFutureApprovalIsRejected(mixed $token, int $storeId, mixed $state, int $now): void
    {
        self::assertFalse((new MigrationApproval())->validate($token, $storeId, $state, $now));
    }

    public static function invalidApprovals(): array
    {
        $token = str_repeat('a', 64);
        $state = ['store_id' => 2, 'created_at' => 1000, 'expires_at' => 1600, 'token_hash' => hash('sha256', $token)];
        return [[null, 2, $state, 1000], [[], 2, $state, 1000], ['', 2, $state, 1000],
            [str_repeat('b', 64), 2, $state, 1000], [str_repeat('A', 64), 2, $state, 1000],
            [$token, 0, $state, 1000], [$token, 3, $state, 1000], [$token, 2, null, 1000],
            [$token, 2, [], 1000], [$token, 2, array_replace($state, ['store_id' => '2']), 1000],
            [$token, 2, array_replace($state, ['created_at' => 1001]), 1000],
            [$token, 2, array_replace($state, ['created_at' => '1000']), 1000],
            [$token, 2, array_replace($state, ['expires_at' => '1600']), 1000],
            [$token, 2, array_replace($state, ['token_hash' => []]), 1000],
            [$token, 2, array_replace($state, ['token_hash' => 'not-a-digest']), 1000]];
    }

    public function testSeparateApprovalCreatesIndependentUnpredictableToken(): void
    {
        $approval = new MigrationApproval();
        $one = $approval->create(2, 1000);
        $two = $approval->create(2, 1000);
        self::assertNotSame($one['token'], $two['token']);
        self::assertFalse($approval->validate($one['token'], 2, $two['state'], 1000));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStores')]
    public function testApprovalCannotBeCreatedForAdministrativeOrInvalidStore(int $storeId): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MigrationApproval())->create($storeId, 1000);
    }

    public static function invalidStores(): array
    {
        return [[0], [-1]];
    }
}
