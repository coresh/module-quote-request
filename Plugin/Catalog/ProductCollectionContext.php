<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Plugin\Catalog;
use Coresh\QuoteRequest\Model\Config;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
/** Supplies category assignments in one batch after pagination; never forces early collection load. */
class ProductCollectionContext
{
    /** getItems() calls load(), which is intercepted again even for already-loaded collections. */
    private array $processing = [];
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection
    ) {
    }
    public function beforeLoad(Collection $subject): void
    {
        if ($this->config->isEnabled((int) $this->storeManager->getStore()->getId()) && !$subject->isLoaded()) {
            $subject->addAttributeToSelect(['coresh_quote_request_enabled', 'coresh_quote_request_label_mode', 'coresh_quote_request_button_label', 'coresh_quote_request_allow_cart', 'coresh_quote_request_show_price']);
        }
    }
    public function afterLoad(Collection $subject, $result)
    {
        if (!$this->config->isEnabled((int) $this->storeManager->getStore()->getId())) {
            return $result;
        }
        $objectId = spl_object_id($subject);
        if (isset($this->processing[$objectId])) {
            return $result;
        }
        $this->processing[$objectId] = true;
        try {
            $products = $subject->getItems();
            $missing = [];
            foreach ($products as $product) {
                if (!$product->hasData('category_ids')) {
                    $missing[(int) $product->getId()] = $product;
                }
            }
            if ($missing === []) {
                return $result;
            }
            $connection = $this->resourceConnection->getConnection();
            $rows = $connection->fetchAll(
                $connection->select()->from($this->resourceConnection->getTableName('catalog_category_product'), ['product_id', 'category_id'])
                    ->where('product_id IN (?)', array_keys($missing))
            );
            $assignments = array_fill_keys(array_keys($missing), []);
            foreach ($rows as $row) {
                $assignments[(int) $row['product_id']][] = (int) $row['category_id'];
            }
            foreach ($missing as $id => $product) {
                $product->setData('category_ids', $assignments[$id]);
            }
            return $result;
        } finally {
            unset($this->processing[$objectId]);
        }
    }
}
