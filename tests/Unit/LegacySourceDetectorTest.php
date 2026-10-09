<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Migration\LegacySourceDetector;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\TestCase;

class LegacySourceDetectorTest extends TestCase
{
    private function detector(bool $tableExists, array $columns, array $modules): LegacySourceDetector
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('isTableExists')->with('prefix_darsh_callprice')->willReturn($tableExists);
        $adapter->method('describeTable')->with('prefix_darsh_callprice')->willReturn(array_fill_keys($columns, []));
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->with('darsh_callprice')->willReturn('prefix_darsh_callprice');
        $list = $this->createMock(ModuleListInterface::class);
        $list->method('getOne')->willReturnCallback(static fn ($name) => in_array($name, $modules, true) ? ['name' => $name] : null);
        return new LegacySourceDetector($resource, $list);
    }

    public function testDarshMigrationIsSupportedOnlyWhenRequiredSchemaIsPresent(): void
    {
        $columns = ['id', 'product_id', 'product_name', 'customer_name', 'customer_email', 'customer_telephone', 'request_detail', 'status'];
        self::assertTrue($this->detector(true, $columns, [])->hasSupportedSource());
        self::assertSame([['name' => 'Darsh_Callforprice', 'supported' => true]],
            $this->detector(true, $columns, [])->getSources());
        array_pop($columns);
        self::assertFalse($this->detector(true, $columns, ['Darsh_Callforprice'])->hasSupportedSource());
        self::assertSame([['name' => 'Darsh_Callforprice', 'supported' => false]],
            $this->detector(true, $columns, ['Darsh_Callforprice'])->getSources());
    }

    public function testUnknownExtensionSchemaIsReportedWithoutInventingAdapterSupport(): void
    {
        self::assertSame([
            ['name' => 'Sparsh_ProductInquiry', 'supported' => false],
            ['name' => 'Sparsh_ProductInquiryPro', 'supported' => false],
        ], $this->detector(false, [], ['Sparsh_ProductInquiry', 'Sparsh_ProductInquiryPro'])->getSources());
    }

    public function testNoModuleOrTableReturnsNoMigrationSources(): void
    {
        self::assertSame([], $this->detector(false, [], [])->getSources());
    }
}
