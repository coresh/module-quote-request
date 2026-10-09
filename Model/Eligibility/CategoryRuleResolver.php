<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Eligibility;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/** One store-scoped category batch per request; no category model loads in product loops. */
class CategoryRuleResolver
{
    private array $categoriesByStore = [];

    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /** A category rule is inherited by descendants inside the current store's root only. */
    public function matches(array $productCategoryIds, int $storeId, array $selectedCategoryIds = []): bool
    {
        if ($productCategoryIds === []) {
            return false;
        }
        $categories = $this->loadCategories($storeId);
        $selected = array_fill_keys(array_map('intval', $selectedCategoryIds), true);
        foreach ($productCategoryIds as $categoryId) {
            $category = $categories[(int) $categoryId] ?? null;
            if ($category === null) {
                continue;
            }
            foreach ($category['ancestors'] as $ancestorId) {
                if (isset($categories[$ancestorId]) && ($categories[$ancestorId]['enabled'] || isset($selected[$ancestorId]))) {
                    return true;
                }
            }
        }
        return false;
    }

    private function loadCategories(int $storeId): array
    {
        if (isset($this->categoriesByStore[$storeId])) {
            return $this->categoriesByStore[$storeId];
        }
        $rootId = (int) $this->storeManager->getStore($storeId)->getRootCategoryId();
        $connection = $this->resourceConnection->getConnection();
        $rootPath = (string) $connection->fetchOne(
            $connection->select()->from($this->resourceConnection->getTableName('catalog_category_entity'), 'path')
                ->where('entity_id = ?', $rootId)
        );
        if ($rootPath === '') {
            return $this->categoriesByStore[$storeId] = [];
        }
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['path', 'coresh_quote_request_enabled']);
        $collection->addFieldToFilter('path', [['eq' => $rootPath], ['like' => $rootPath . '/%']]);
        $categories = [];
        foreach ($collection as $category) {
            $pathIds = array_map('intval', explode('/', (string) $category->getPath()));
            $rootIndex = array_search($rootId, $pathIds, true);
            if ($rootIndex === false) {
                continue;
            }
            $categories[(int) $category->getId()] = [
                'enabled' => (bool) $category->getData('coresh_quote_request_enabled'),
                'ancestors' => array_slice($pathIds, $rootIndex)
            ];
        }
        return $this->categoriesByStore[$storeId] = $categories;
    }
}
