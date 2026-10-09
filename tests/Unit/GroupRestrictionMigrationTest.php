<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Setup\Patch\Data\PreserveCustomerGroupRestrictions;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;

class GroupRestrictionMigrationTest extends TestCase
{
    private function patch(array $existingFlags, array &$insertions): PreserveCustomerGroupRestrictions
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->expects(self::once())->method('startSetup');
        $connection->expects(self::once())->method('endSetup');
        $connection->method('fetchAll')->willReturn([
            ['scope' => 'default', 'scope_id' => 0, 'value' => '1,2'],
            ['scope' => 'stores', 'scope_id' => 2, 'value' => ''],
            ['scope' => 'websites', 'scope_id' => 3, 'value' => '0,3'],
        ]);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(...$existingFlags);
        $connection->method('insertOnDuplicate')->willReturnCallback(static function ($table, $data, $fields) use (&$insertions) {
            self::assertSame('core_config_data', $table);
            // A conflict must not overwrite a concurrent administrator's explicit flag value.
            self::assertSame(['path'], $fields);
            $insertions[] = $data;
            return 1;
        });
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('core_config_data')->willReturn('core_config_data');
        return new PreserveCustomerGroupRestrictions($setup);
    }

    public function testNonemptyGroupsPreserveRestrictionAndExplicitEmptyChildAllowsAll(): void
    {
        $insertions = [];
        $patch = $this->patch([false, false, 42], $insertions);
        self::assertSame($patch, $patch->apply());
        self::assertSame([
            ['scope' => 'default', 'scope_id' => 0, 'path' => PreserveCustomerGroupRestrictions::RESTRICT_PATH, 'value' => '1'],
            ['scope' => 'stores', 'scope_id' => 2, 'path' => PreserveCustomerGroupRestrictions::RESTRICT_PATH, 'value' => '0'],
        ], $insertions);
    }

    public function testRerunKeepsExistingExplicitValuesWithoutWritingDuplicates(): void
    {
        $insertions = [];
        $this->patch([1, 2, 3], $insertions)->apply();
        self::assertSame([], $insertions);
    }
}
