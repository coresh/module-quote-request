<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Migration\LegacyDataMigrator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class LegacyDateImportTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('legacyDates')]
    public function testStrictLegacyDateParsingDoesNotThrowForMalformedSource(string $source, ?string $expected): void
    {
        $reflection = new \ReflectionClass(LegacyDataMigrator::class);
        $migrator = $reflection->newInstanceWithoutConstructor();
        $result = $reflection->getMethod('parseLegacyDate')->invoke($migrator, $source);
        self::assertSame($expected, $result?->format('Y-m-d'));
    }

    public static function legacyDates(): array
    {
        return [
            ['10/09/2026', '2026-10-09'], ['2026-10-09', '2026-10-09'],
            ['02/29/2024', '2024-02-29'], ['2024-02-29', '2024-02-29'],
            ["10/09/2026\0", null], ["2026-10-09\0", null], ["\0", null],
            ['02/29/2026', null], ['2026-02-29', null], ['13/09/2026', null],
            ['2026-10-32', null], ['2026-10-09 extra', null], [' 2026-10-09', null],
            ['2026-10-09 ', null], ["2026-10-09\n", null], ['10/9/2026', null],
            ['2026-1-09', null], ['', null]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('importModes')]
    public function testMalformedDateIsSkippedWithoutAbortingOtherConfiguration(bool $execute): void
    {
        $paths = new \SplObjectStorage();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () use ($paths) {
            $select = $this->createMock(Select::class);
            $paths[$select] = '';
            $select->method('from')->willReturnSelf();
            $select->method('where')->willReturnCallback(static function ($condition, $value) use ($select, $paths) {
                if ($condition === 'path = ?') {
                    $paths[$select] = $value;
                }
                return $select;
            });
            return $select;
        });
        $rows = [
            'callforprice/call_for_price/enable' => '1',
            'callforprice/call_for_price/from_date' => "10/09/2026\0",
            'callforprice/call_for_price/to_date' => '10/31/2026'
        ];
        $connection->method('fetchAll')->willReturnCallback(static function ($select) use ($rows, $paths): array {
            $path = $paths[$select];
            return isset($rows[$path]) ? [['scope' => 'default', 'scope_id' => 0,
                'path' => $path, 'value' => $rows[$path]]] : [];
        });
        $connection->method('fetchOne')->willReturn(false);
        $writes = [];
        $connection->expects($execute ? self::exactly(2) : self::never())->method('insertOnDuplicate')
            ->willReturnCallback(static function ($table, array $row, array $fields) use (&$writes): int {
                self::assertSame('core_config_data', $table);
                self::assertSame(['path'], $fields);
                $writes[$row['path']] = $row['value'];
                return 1;
            });
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('core_config_data');
        $reflection = new \ReflectionClass(LegacyDataMigrator::class);
        $migrator = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('resource')->setValue($migrator, $resource);
        self::assertSame(2, $reflection->getMethod('migrateConfiguration')->invoke($migrator, $execute));
        self::assertSame($execute ? [
            'coresh_quote_request/general/enabled' => '1',
            'coresh_quote_request/general/to_date' => '2026-10-31'
        ] : [], $writes);
    }

    public static function importModes(): array { return [[false], [true]]; }
}
