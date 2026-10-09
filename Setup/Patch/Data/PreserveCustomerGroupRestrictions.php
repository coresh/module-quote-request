<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Preserves v1 semantics at each explicit config scope without overriding a new explicit restriction flag. */
class PreserveCustomerGroupRestrictions implements DataPatchInterface
{
    public const GROUPS_PATH = 'coresh_quote_request/general/customer_groups';
    public const RESTRICT_PATH = 'coresh_quote_request/general/restrict_customer_groups';

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();
        try {
            $table = $this->moduleDataSetup->getTable('core_config_data');
            $rows = $connection->fetchAll(
                $connection->select()->from($table, ['scope', 'scope_id', 'value'])->where('path = ?', self::GROUPS_PATH)
            );
            foreach ($rows as $row) {
                $scope = (string) $row['scope'];
                $scopeId = (int) $row['scope_id'];
                $exists = $connection->fetchOne(
                    $connection->select()->from($table, 'config_id')->where('scope = ?', $scope)
                        ->where('scope_id = ?', $scopeId)->where('path = ?', self::RESTRICT_PATH)
                );
                if ($exists !== false && $exists !== null) {
                    continue;
                }
                // An explicit empty v1 child selection allowed all even if its parent scope restricted groups.
                // On duplicate the only updated field is the same path; a concurrently saved flag keeps its value.
                $connection->insertOnDuplicate($table, [
                    'scope' => $scope,
                    'scope_id' => $scopeId,
                    'path' => self::RESTRICT_PATH,
                    'value' => trim((string) ($row['value'] ?? '')) === '' ? '0' : '1'
                ], ['path']);
            }
        } finally {
            $connection->endSetup();
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
