<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Catalog;

use Coresh\QuoteRequest\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;

/** Store-context product presentation overrides, separate from group/date/category eligibility. */
class ProductDisplayPolicy
{
    public const LABEL_MODE = 'coresh_quote_request_label_mode';
    public const BUTTON_LABEL = 'coresh_quote_request_button_label';
    public const ALLOW_CART = 'coresh_quote_request_allow_cart';
    public const SHOW_PRICE = 'coresh_quote_request_show_price';

    public function __construct(private readonly Config $config)
    {
    }

    /** Custom mode deliberately preserves an empty label as "hide enquiry". */
    public function getButtonLabel(ProductInterface $product, int $storeId): string
    {
        if ($this->usesProductOverrides($product) && $this->getAttributeValue($product, self::LABEL_MODE) === 'custom') {
            $label = $this->getAttributeValue($product, self::BUTTON_LABEL);
            return is_scalar($label) ? trim((string) $label) : '';
        }
        return $this->config->getButtonLabel($storeId);
    }

    /** Caller must additionally apply current module/group/date/product/category eligibility. */
    public function isInquiryAllowed(ProductInterface $product, int $storeId): bool
    {
        return $this->config->getValue('general/request_display', $storeId) !== 'label'
            && $this->getButtonLabel($product, $storeId) !== '';
    }

    /** Product Yes never bypasses the global server-side purchasing restriction. */
    public function isAddToCartAllowed(ProductInterface $product, int $storeId): bool
    {
        if (!$this->config->isEnabled($storeId)) {
            return true;
        }
        if ($this->config->isPurchasingDisabled($storeId)) {
            return false;
        }
        if ($this->usesProductOverrides($product)) {
            $override = $this->getTriState($product, self::ALLOW_CART);
            if ($override !== null) {
                return $override;
            }
        }
        return $this->config->isFlag('catalog/allow_add_to_cart', $storeId);
    }

    /** Caller gates this display-only setting by base eligibility; it is not API price confidentiality. */
    public function shouldHidePrice(ProductInterface $product, int $storeId): bool
    {
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }
        if ($this->usesProductOverrides($product)) {
            $disclose = $this->getTriState($product, self::SHOW_PRICE);
            if ($disclose !== null) {
                return !$disclose;
            }
        }
        return $this->config->shouldHidePrices($storeId);
    }

    private function usesProductOverrides(ProductInterface $product): bool
    {
        $enabled = $this->getAttributeValue($product, 'coresh_quote_request_enabled');
        return in_array($enabled, [1, '1', true], true);
    }

    private function getTriState(ProductInterface $product, string $code): ?bool
    {
        $value = $this->getAttributeValue($product, $code);
        if (in_array($value, [1, '1', true], true)) {
            return true;
        }
        if (in_array($value, [0, '0', false], true)) {
            return false;
        }
        return null;
    }

    private function getAttributeValue(ProductInterface $product, string $code): mixed
    {
        return $product->getCustomAttribute($code)?->getValue();
    }
}
