<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Catalog;
use Magento\Framework\App\ResourceConnection;
/** Reads persisted origin directly; repository instances may have an in-memory changed store. */
class QuoteStoreResolver
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }
    public function getPersistedStoreId(int $quoteId): ?int
    {
        if ($quoteId <= 0) {
            return null;
        }
        $connection = $this->resourceConnection->getConnection();
        $storeId = $connection->fetchOne(
            $connection->select()->from($this->resourceConnection->getTableName('quote'), 'store_id')
                ->where('entity_id = ?', $quoteId)
        );
        return $storeId === false || $storeId === null ? null : (int) $storeId;
    }
}
