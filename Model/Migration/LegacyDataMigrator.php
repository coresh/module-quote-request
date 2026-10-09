<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Migration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/** Explicit, non-destructive import. Never sends mail or deletes legacy rows. */
class LegacyDataMigrator
{
    private const CONFIG_MAP = [
        'callforprice/call_for_price/enable' => 'general/enabled',
        'callforprice/call_for_price/call_for_price_button_text' => 'general/button_label',
        'callforprice/call_for_price/send_email_to' => 'email/recipient',
        'callforprice/call_for_price/email_sender' => 'email/sender_identity',
        'callforprice/call_for_price/customer_groups' => 'general/customer_groups',
        'callforprice/call_for_price/specific_date_range' => 'general/use_date_range',
        'callforprice/call_for_price/from_date' => 'general/from_date',
        'callforprice/call_for_price/to_date' => 'general/to_date',
    ];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array<string,int|string> */
    public function migrate(int $storeId, bool $execute): array
    {
        $store = $this->storeManager->getStore($storeId);
        if (!$storeId || !$store->isActive()) {
            throw new LocalizedException(__('Choose an active storefront store ID.'));
        }
        $connection = $this->resource->getConnection();
        $target = $this->resource->getTableName('coresh_quote_request');
        if (!$connection->isTableExists($target)) {
            throw new LocalizedException(__('Install Coresh_QuoteRequest before importing.'));
        }
        $report = ['mode' => $execute ? 'execute' : 'dry-run', 'requests' => 0,
            'already_imported' => 0, 'invalid_rows' => 0, 'attribute_values' => 0, 'config_values' => 0];
        $source = $this->resource->getTableName('darsh_callprice');
        if ($connection->isTableExists($source)) {
            $requiredColumns = ['id', 'product_id', 'product_name', 'customer_name',
                'customer_email', 'customer_telephone', 'request_detail', 'status'];
            if (array_diff($requiredColumns, array_keys($connection->describeTable($source))) !== []) {
                throw new LocalizedException(__('The legacy source schema does not match the verified adapter.'));
            }
            $lastId = 0;
            do {
                $rows = $connection->fetchAll($connection->select()->from($source)
                    ->where('id > ?', $lastId)->order('id ASC')->limit(100));
                foreach ($rows as $row) {
                    $legacyId = (int) $row['id'];
                    $lastId = $legacyId;
                    if ($connection->fetchOne($connection->select()->from($target, ['request_id'])
                        ->where('legacy_id = ?', $legacyId))) {
                        ++$report['already_imported'];
                        continue;
                    }
                    $email = trim((string) ($row['customer_email'] ?? ''));
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
                        || preg_match('/[\r\n]/', $email)) {
                        ++$report['invalid_rows'];
                        continue;
                    }
                    $productId = max(0, (int) ($row['product_id'] ?? 0));
                    $snapshot = ['product_sku' => '', 'product_url' => '', 'product_type' => 'legacy'];
                    if ($productId) {
                        try {
                            $product = $this->productRepository->getById($productId, false, $storeId);
                            $snapshot = ['product_sku' => mb_substr((string) $product->getSku(), 0, 255),
                                'product_url' => (string) $product->getProductUrl(),
                                'product_type' => (string) $product->getTypeId()];
                        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                            // Historic product may have been removed. Retain its original logical ID.
                        }
                    }
                    $data = $snapshot + [
                        'legacy_id' => $legacyId, 'store_id' => $storeId, 'customer_id' => null,
                        'product_id' => $productId ?: null,
                        'product_name' => mb_substr((string) ($row['product_name'] ?? ''), 0, 255),
                        'quantity' => null, 'quantity_origin' => 'legacy',
                        'customer_name' => mb_substr((string) ($row['customer_name'] ?? ''), 0, 128),
                        'customer_email' => $email,
                        'customer_telephone' => mb_substr((string) ($row['customer_telephone'] ?? ''), 0, 64),
                        'message' => (string) ($row['request_detail'] ?? ''),
                        'request_status' => match ((string) ($row['status'] ?? '1')) {
                            '2' => 'closed', default => 'new'
                        },
                        'email_status' => 'legacy', 'email_attempts' => 0,
                    ];
                    if ($execute) {
                        // Unique legacy_id also protects against concurrent importer invocations.
                        $connection->insertOnDuplicate($target, $data, ['legacy_id']);
                    }
                    ++$report['requests'];
                }
            } while (count($rows) === 100);
        }
        $report['attribute_values'] = $this->migrateAttributeValues($execute);
        $report['config_values'] = $this->migrateConfiguration($execute);
        return $report;
    }

    private function migrateAttributeValues(bool $execute): int
    {
        $connection = $this->resource->getConnection();
        $count = 0;
        foreach (['catalog_product', 'catalog_category'] as $entity) {
            $old = $this->eavConfig->getAttribute($entity, 'call_for_price');
            $new = $this->eavConfig->getAttribute($entity, 'coresh_quote_request_enabled');
            if (!$old->getId() || !$new->getId()) {
                continue;
            }
            $table = $this->resource->getTableName($entity . '_entity_int');
            $columns = $connection->describeTable($table);
            if (!isset($columns['entity_id'])) {
                // Adobe Commerce staging row_id requires a separate verified migration adapter.
                throw new LocalizedException(__('Legacy attribute migration supports Open Source entity_id tables.'));
            }
            $select = $connection->select()->from(['legacy' => $table], ['entity_id', 'store_id', 'value'])
                ->joinLeft(['current' => $table],
                    'current.entity_id = legacy.entity_id AND current.store_id = legacy.store_id'
                    . ' AND current.attribute_id = ' . (int) $new->getId(), [])
                ->where('legacy.attribute_id = ?', (int) $old->getId())->where('current.value_id IS NULL');
            $statement = $connection->query($select);
            while ($row = $statement->fetch()) {
                if ($execute) {
                    $connection->insertOnDuplicate($table,
                        ['attribute_id' => (int) $new->getId(), 'entity_id' => (int) $row['entity_id'],
                            'store_id' => (int) $row['store_id'], 'value' => (int) (bool) $row['value']],
                        ['attribute_id']);
                }
                ++$count;
            }
        }
        if ($execute) {
            $this->eavConfig->clear();
        }
        return $count;
    }

    private function migrateConfiguration(bool $execute): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('core_config_data');
        $count = 0;
        foreach (self::CONFIG_MAP as $oldPath => $newRelativePath) {
            foreach ($connection->fetchAll($connection->select()->from($table)->where('path = ?', $oldPath)) as $row) {
                $newPath = 'coresh_quote_request/' . $newRelativePath;
                if ($connection->fetchOne($connection->select()->from($table, ['config_id'])
                    ->where('scope = ?', $row['scope'])->where('scope_id = ?', (int) $row['scope_id'])
                    ->where('path = ?', $newPath))) {
                    continue;
                }
                $value = (string) $row['value'];
                if (in_array($newRelativePath, ['general/from_date', 'general/to_date'], true) && $value !== '') {
                    $date = $this->parseLegacyDate($value);
                    if ($date === null) {
                        continue;
                    }
                    $value = $date->format('Y-m-d');
                }
                if ($execute) {
                    $connection->insertOnDuplicate($table,
                        ['scope' => $row['scope'], 'scope_id' => (int) $row['scope_id'],
                            'path' => $newPath, 'value' => $value], ['path']);
                }
                ++$count;
                if ($newRelativePath === 'general/customer_groups') {
                    $restrictionPath = 'coresh_quote_request/general/restrict_customer_groups';
                    $exists = $connection->fetchOne($connection->select()->from($table, ['config_id'])
                        ->where('scope = ?', $row['scope'])->where('scope_id = ?', (int) $row['scope_id'])
                        ->where('path = ?', $restrictionPath));
                    if (!$exists) {
                        if ($execute) {
                            $connection->insertOnDuplicate($table,
                                ['scope' => $row['scope'], 'scope_id' => (int) $row['scope_id'],
                                    'path' => $restrictionPath, 'value' => trim($value) === '' ? '0' : '1'],
                                ['path']);
                        }
                        ++$count;
                    }
                }
            }
        }
        return $count;
    }

    private function parseLegacyDate(string $value): ?\DateTimeImmutable
    {
        foreach (['m/d/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false
                || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value
            ) {
                return $date;
            }
        }
        return null;
    }
}
