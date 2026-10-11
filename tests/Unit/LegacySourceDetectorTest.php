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
    private const REQUIRED_COLUMNS = ['id', 'product_id', 'product_name', 'customer_name',
        'customer_email', 'customer_telephone', 'request_detail', 'status'];

    private function detector(bool $tableExists, array $columns, array $modules): LegacySourceDetector
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects(self::once())->method('isTableExists')
            ->with('prefix_darsh_callprice')->willReturn($tableExists);
        $adapter->expects($tableExists ? self::once() : self::never())->method('describeTable')
            ->with('prefix_darsh_callprice')->willReturn(array_fill_keys($columns, []));
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->with('darsh_callprice')->willReturn('prefix_darsh_callprice');
        $list = $this->createMock(ModuleListInterface::class);
        $list->method('getOne')->willReturnCallback(static fn ($name) => in_array($name, $modules, true) ? ['name' => $name] : null);
        return new LegacySourceDetector($resource, $list);
    }

    public function testVerifiedLegacySchemaIsDetectedWithNeutralLabel(): void
    {
        self::assertTrue($this->detector(true, self::REQUIRED_COLUMNS, [])->hasSupportedSource());
        self::assertSame([['name' => 'Legacy quote request data', 'supported' => true]],
            $this->detector(true, self::REQUIRED_COLUMNS, [])->getSources());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingRequiredColumns')]
    public function testEveryRequiredColumnIsMandatoryForImportSupport(string $missing): void
    {
        $columns = array_values(array_diff(self::REQUIRED_COLUMNS, [$missing]));
        self::assertFalse($this->detector(true, $columns, [])->hasSupportedSource());
        self::assertSame([['name' => 'Legacy quote request data', 'supported' => false]],
            $this->detector(true, $columns, [])->getSources());
    }

    public static function missingRequiredColumns(): array
    {
        return array_map(static fn (string $column): array => [$column], self::REQUIRED_COLUMNS);
    }

    public function testAdditionalColumnsDoNotRemoveVerifiedCompatibility(): void
    {
        self::assertTrue($this->detector(true, [...self::REQUIRED_COLUMNS, 'created_at'], [])
            ->hasSupportedSource());
    }

    public function testRegisteredUnverifiedExtensionDoesNotEnableImportWithoutSourceTable(): void
    {
        self::assertFalse($this->detector(false, [], ['Vendor_UnverifiedEnquiry'])->hasSupportedSource());
        self::assertSame([], $this->detector(false, [], ['Vendor_UnverifiedEnquiry'])->getSources());
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
