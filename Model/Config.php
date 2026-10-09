<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/** All configuration reads use Magento's store/website/default inheritance. */
class Config
{
    public const PREFIX = 'coresh_quote_request/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getValue(string $relativePath, ?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(self::PREFIX . $relativePath, ScopeInterface::SCOPE_STORE, $storeId);
        return is_scalar($value) ? (string) $value : '';
    }

    public function isFlag(string $relativePath, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . $relativePath, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isFlag('general/enabled', $storeId);
    }

    public function isCartHidden(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->isFlag('catalog/hide_cart', $storeId);
    }

    public function isPurchasingDisabled(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->isFlag('catalog/disable_purchasing', $storeId);
    }

    public function shouldHidePrices(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->isFlag('catalog/hide_prices', $storeId);
    }

    public function getButtonLabel(?int $storeId = null): string
    {
        return trim($this->getValue('general/button_label', $storeId)) ?: 'Contact Us for a Quote';
    }

    public function getRecipient(int $storeId): string
    {
        $configured = trim($this->getValue('email/recipient', $storeId));
        if ($configured !== '') {
            return $configured;
        }
        $fallback = $this->scopeConfig->getValue('contacts/email/recipient_email', ScopeInterface::SCOPE_STORE, $storeId);
        return is_scalar($fallback) ? trim((string) $fallback) : '';
    }
}
