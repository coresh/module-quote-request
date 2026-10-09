<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class MassStatusResourceTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBatches')]
    public function testInvalidBatchNeverReachesDatabase(array $ids, string $status): void
    {
        $resource = $this->getMockBuilder(QuoteRequest::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])->getMock();
        $resource->expects(self::never())->method('getConnection');
        $this->expectException(\InvalidArgumentException::class);
        $resource->updateRequestStatuses($ids, $status);
    }

    public static function invalidBatches(): array
    {
        return [[['1'], 'approved'], [[0], 'new'], [[-1], 'new'], [['001'], 'closed'], [[[]], 'new'],
            [[true], 'new'], [['1 OR 1=1'], 'new'], [['4294967296'], 'new'], [[1.0], 'closed'],
            [range(1, 1001), 'processing']];
    }

    public function testEmptySelectionReturnsZeroWithoutDatabaseMutation(): void
    {
        $resource = $this->getMockBuilder(QuoteRequest::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])->getMock();
        $resource->expects(self::never())->method('getConnection');
        self::assertSame(0, $resource->updateRequestStatuses([], 'closed'));
    }

    public function testCanonicalDeduplicatedBatchChangesOnlyHandlingState(): void
    {
        $resource = $this->getMockBuilder(QuoteRequest::class)->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])->getMock();
        $adapter = $this->createMock(AdapterInterface::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getMainTable')->willReturn('coresh_quote_request');
        $adapter->expects(self::once())->method('update')->with('coresh_quote_request',
            self::callback(static function (array $changes): bool {
                $keys = array_keys($changes);
                sort($keys);
                return $keys === ['request_status', 'updated_at'] && $changes['request_status'] === 'processing';
            }), ['request_id IN (?)' => [1, 2]])->willReturn(2);
        self::assertSame(2, $resource->updateRequestStatuses([1, '1', '2'], 'processing'));
    }
}
