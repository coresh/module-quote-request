<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Product;

use Coresh\QuoteRequest\Model\Eligibility\QuoteRequestEligibility;
use Coresh\QuoteRequest\Model\Catalog\ProductDisplayPolicy;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/** Resolves authoritative catalogue snapshots, never accepting names, URLs or identity from POST. */
class ProductContextResolver
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly StoreManagerInterface $stores,
        private readonly QuoteRequestEligibility $eligibility,
        private readonly Configurable $configurableType,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ProductDisplayPolicy $displayPolicy
    ) {
    }

    public function resolve(int $productId, int $storeId, int $groupId, array $superAttributes = []): array
    {
        try {
            $store = $this->stores->getStore($storeId);
            $product = $this->products->getById($productId, false, $storeId, true);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('This product is unavailable for a quote request.'));
        }
        if (!$product instanceof Product || !$store->isActive()) {
            throw new LocalizedException(__('This product is unavailable for a quote request.'));
        }
        $websiteId = (int) $store->getWebsiteId();
        $this->assertAvailable($product, $websiteId, true);
        if (!$this->eligibility->canRequestQuote($product, $storeId, $groupId)
            || !$this->displayPolicy->isInquiryAllowed($product, $storeId)
        ) {
            throw new LocalizedException(__('Quote requests are unavailable for this product.'));
        }
        $type = (string) $product->getTypeId();
        if (!in_array($type, ['simple', 'virtual', 'downloadable', 'configurable', 'bundle', 'grouped'], true)) {
            throw new LocalizedException(__('This product type is unavailable for a quote request.'));
        }
        foreach (['name', 'sku'] as $field) {
            $snapshot = (string) $product->getData($field);
            if (!mb_check_encoding($snapshot, 'UTF-8') || mb_strlen($snapshot, 'UTF-8') > 255
                || preg_match('/[\x00-\x1F\x7F]/u', $snapshot)
            ) {
                throw new LocalizedException(__('The product information is unavailable for a quote request.'));
            }
        }
        $context = [
            'product_id' => (int) $product->getId(),
            'product_name' => (string) $product->getName(),
            'product_sku' => (string) $product->getSku(),
            'product_type' => $type,
            'product_url' => (string) $product->getProductUrl(),
            'selected_product_id' => null,
            'selected_product_sku' => null,
            'selected_options' => null
        ];
        if ($type === 'configurable' && $superAttributes !== []) {
            return $this->resolveSelection($product, $storeId, $websiteId, $superAttributes, $context);
        }
        if ($superAttributes !== []) {
            throw new LocalizedException(__('Product options are invalid.'));
        }
        if (in_array($type, ['bundle', 'grouped', 'configurable'], true)) {
            $context['selected_options'] = json_encode(['inquiry_scope' => 'parent_only'], JSON_THROW_ON_ERROR);
        }
        return $context;
    }

    /** Fractional enquiry units follow product stock metadata without reserving or checking availability. */
    public function assertQuantityAllowed(array $context, string $quantity, int $storeId): void
    {
        $fraction = explode('.', $quantity, 2)[1] ?? '0000';
        if ($fraction === '0000') {
            return;
        }
        $itemId = (int) ($context['selected_product_id'] ?? 0);
        if ($itemId < 1) {
            if (!in_array($context['product_type'], ['simple', 'virtual', 'downloadable'], true)) {
                throw new LocalizedException(__('Please request a whole number of parent product units.'));
            }
            $itemId = (int) $context['product_id'];
        }
        $websiteId = (int) $this->stores->getStore($storeId)->getWebsiteId();
        if (!$this->stockRegistry->getStockItem($itemId, $websiteId)->getIsQtyDecimal()) {
            throw new LocalizedException(__('This product requires a whole number of units.'));
        }
    }

    private function assertAvailable(Product $product, int $websiteId, bool $visible): void
    {
        $websites = array_map('intval', $product->getWebsiteIds());
        if (!in_array($websiteId, $websites, true)
            || (int) $product->getStatus() !== Status::STATUS_ENABLED
            || ($visible && !in_array((int) $product->getVisibility(), [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_IN_SEARCH,
                Visibility::VISIBILITY_BOTH
            ], true))
        ) {
            throw new LocalizedException(__('This product is unavailable for a quote request.'));
        }
    }

    private function resolveSelection(
        Product $parent,
        int $storeId,
        int $websiteId,
        array $selection,
        array $context
    ): array {
        $attributes = [];
        foreach ($this->configurableType->getConfigurableAttributes($parent) as $configurableAttribute) {
            $attribute = $configurableAttribute->getProductAttribute();
            $attributes[(int) $attribute->getAttributeId()] = $attribute;
        }
        $selectedKeys = array_map('intval', array_keys($selection));
        $attributeKeys = array_keys($attributes);
        sort($selectedKeys);
        sort($attributeKeys);
        if ($selectedKeys !== $attributeKeys || $attributeKeys === []) {
            throw new LocalizedException(__('Please select all valid product options.'));
        }
        foreach ($this->configurableType->getUsedProducts($parent) as $candidate) {
            $matches = true;
            foreach ($attributes as $attributeId => $attribute) {
                if ((int) $candidate->getData($attribute->getAttributeCode()) !== $selection[$attributeId]) {
                    $matches = false;
                    break;
                }
            }
            if (!$matches) {
                continue;
            }
            try {
                $child = $this->products->getById((int) $candidate->getId(), false, $storeId, true);
            } catch (NoSuchEntityException $exception) {
                break;
            }
            if (!$child instanceof Product) {
                break;
            }
            $this->assertAvailable($child, $websiteId, false);
            $childSku = (string) $child->getSku();
            if (!mb_check_encoding($childSku, 'UTF-8') || mb_strlen($childSku, 'UTF-8') > 255
                || preg_match('/[\x00-\x1F\x7F]/u', $childSku)
            ) {
                throw new LocalizedException(__('The selected product information is unavailable.'));
            }
            $options = [];
            foreach ($attributes as $attributeId => $attribute) {
                $value = $child->getAttributeText($attribute->getAttributeCode());
                if (!is_scalar($value) || $value === false || $value === '') {
                    throw new LocalizedException(__('The selected product options are unavailable.'));
                }
                $options[] = [
                    'attribute_id' => $attributeId,
                    'attribute_code' => (string) $attribute->getAttributeCode(),
                    'label' => (string) $attribute->getStoreLabel($storeId),
                    'option_id' => (int) $selection[$attributeId],
                    'value' => (string) $value
                ];
            }
            $context['selected_product_id'] = (int) $child->getId();
            $context['selected_product_sku'] = $childSku;
            $context['selected_options'] = json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            return $context;
        }
        throw new LocalizedException(__('The selected product options are unavailable.'));
    }
}
