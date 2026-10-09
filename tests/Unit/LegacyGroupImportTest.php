<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Migration\LegacyDataMigrator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

/** Exercises the real importer method; DB doubles isolate config scope/overwrite behaviour. */
class LegacyGroupImportTest extends TestCase
{
    private const OLD_PATH = 'callforprice/call_for_price/customer_groups';
    private const NEW_PATH = 'coresh_quote_request/general/customer_groups';
    private const RESTRICT_PATH = 'coresh_quote_request/general/restrict_customer_groups';

    private function key(string $scope, int $scopeId, string $path): string
    {
        return $scope . ':' . $scopeId . ':' . $path;
    }

    private function row(string $scope, int $scopeId, string $path, string $value): array
    {
        return ['config_id' => 10, 'scope' => $scope, 'scope_id' => $scopeId, 'path' => $path, 'value' => $value];
    }

    /** Initializes only the dependency used by migrateConfiguration; unrelated migration services are never called. */
    private function harness(array $initialRows): array
    {
        $state = (object) ['rows' => [], 'writes' => [], 'nextId' => 100];
        foreach ($initialRows as $row) {
            $state->rows[$this->key($row['scope'], (int) $row['scope_id'], $row['path'])] = $row;
        }
        $queries = new \SplObjectStorage();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function () use ($queries) {
            $select = $this->createMock(Select::class);
            $queries[$select] = [];
            $select->method('from')->willReturnSelf();
            $select->method('where')->willReturnCallback(static function ($condition, $value) use ($select, $queries) {
                $filters = $queries[$select];
                $filters[trim(str_replace(' = ?', '', $condition))] = $value;
                $queries[$select] = $filters;
                return $select;
            });
            return $select;
        });
        $connection->method('fetchAll')->willReturnCallback(static function ($select) use ($state, $queries) {
            $filters = $queries[$select];
            return array_values(array_filter($state->rows, static function (array $row) use ($filters): bool {
                foreach ($filters as $field => $expected) {
                    if ((string) $row[$field] !== (string) $expected) {
                        return false;
                    }
                }
                return true;
            }));
        });
        $connection->method('fetchOne')->willReturnCallback(static function ($select) use ($state, $queries) {
            $filters = $queries[$select];
            foreach ($state->rows as $row) {
                $matches = true;
                foreach ($filters as $field => $expected) {
                    $matches = $matches && (string) $row[$field] === (string) $expected;
                }
                if ($matches) {
                    return $row['config_id'];
                }
            }
            return false;
        });
        $connection->method('insertOnDuplicate')->willReturnCallback(function ($table, array $row, array $fields) use ($state) {
            self::assertSame('core_config_data', $table);
            $key = $this->key($row['scope'], (int) $row['scope_id'], $row['path']);
            $state->writes[] = [$row, $fields];
            if (isset($state->rows[$key])) {
                foreach ($fields as $field) {
                    $state->rows[$key][$field] = $row[$field];
                }
            } else {
                $state->rows[$key] = ['config_id' => ++$state->nextId] + $row;
            }
            return 1;
        });
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('core_config_data')->willReturn('core_config_data');
        $reflection = new \ReflectionClass(LegacyDataMigrator::class);
        $migrator = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('resource')->setValue($migrator, $resource);
        $method = $reflection->getMethod('migrateConfiguration');
        $invoke = static fn (bool $execute): int => $method->invoke($migrator, $execute);
        return [$state, $invoke];
    }

    public function testImportAfterUpgradePatchDerivesRestrictionForGuestAndRegisteredGroups(): void
    {
        // The upgrade patch was recorded before any legacy groups were copied; no explicit new flag row exists.
        [$state, $import] = $this->harness([$this->row('websites', 4, self::OLD_PATH, '0,1')]);
        self::assertSame(2, $import(true));
        self::assertSame('0,1', $state->rows[$this->key('websites', 4, self::NEW_PATH)]['value']);
        self::assertSame('1', $state->rows[$this->key('websites', 4, self::RESTRICT_PATH)]['value']);
        self::assertCount(2, $state->writes);
        self::assertSame(['path'], $state->writes[1][1]);
    }

    public function testExplicitEmptyChildGroupsRemainUnrestrictedBeneathRestrictedParent(): void
    {
        [$state, $import] = $this->harness([
            $this->row('default', 0, self::NEW_PATH, '1,2'),
            $this->row('default', 0, self::RESTRICT_PATH, '1'),
            $this->row('stores', 7, self::OLD_PATH, '')
        ]);
        self::assertSame(2, $import(true));
        self::assertSame('', $state->rows[$this->key('stores', 7, self::NEW_PATH)]['value']);
        self::assertSame('0', $state->rows[$this->key('stores', 7, self::RESTRICT_PATH)]['value']);
        self::assertSame('1', $state->rows[$this->key('default', 0, self::RESTRICT_PATH)]['value']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('explicitFlags')]
    public function testAnExplicitNewRestrictionIsPreserved(string $legacyGroups, string $explicitFlag): void
    {
        [$state, $import] = $this->harness([
            $this->row('stores', 2, self::OLD_PATH, $legacyGroups),
            $this->row('stores', 2, self::RESTRICT_PATH, $explicitFlag)
        ]);
        self::assertSame(1, $import(true));
        self::assertSame($explicitFlag, $state->rows[$this->key('stores', 2, self::RESTRICT_PATH)]['value']);
        self::assertCount(1, $state->writes);
    }

    public static function explicitFlags(): array
    {
        return [['1', '0'], ['', '1']];
    }

    public function testRerunDoesNotOverwriteAdminChangesToImportedGroupsOrRestriction(): void
    {
        [$state, $import] = $this->harness([$this->row('stores', 3, self::OLD_PATH, '0,1')]);
        self::assertSame(2, $import(true));
        $state->rows[$this->key('stores', 3, self::NEW_PATH)]['value'] = '2';
        $state->rows[$this->key('stores', 3, self::RESTRICT_PATH)]['value'] = '0';
        self::assertSame(0, $import(true));
        self::assertSame('2', $state->rows[$this->key('stores', 3, self::NEW_PATH)]['value']);
        self::assertSame('0', $state->rows[$this->key('stores', 3, self::RESTRICT_PATH)]['value']);
        self::assertCount(2, $state->writes);
    }

    public function testDryRunCountsGroupAndDerivedFlagWithoutWritingEither(): void
    {
        [$state, $import] = $this->harness([$this->row('default', 0, self::OLD_PATH, '0')]);
        self::assertSame(2, $import(false));
        self::assertSame([], $state->writes);
        self::assertArrayNotHasKey($this->key('default', 0, self::NEW_PATH), $state->rows);
        self::assertArrayNotHasKey($this->key('default', 0, self::RESTRICT_PATH), $state->rows);
    }
}
