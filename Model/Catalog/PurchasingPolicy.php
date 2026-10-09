<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Catalog;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/** A single server policy shared by storefront, REST and GraphQL entry points. */
class PurchasingPolicy
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
    }

    public function assertAllowed(?int $quoteStoreId = null): void
    {
        if ($this->isExemptArea()) {
            return;
        }
        $currentStoreId = (int) $this->storeManager->getStore()->getId();
        if ($this->config->isPurchasingDisabled($currentStoreId)
            || ($quoteStoreId !== null && $this->config->isPurchasingDisabled($quoteStoreId))
        ) {
            throw new LocalizedException(__('Online purchasing is currently unavailable. Please request a quote.'));
        }
    }

    private function isExemptArea(): bool
    {
        try {
            return in_array($this->appState->getAreaCode(), [Area::AREA_ADMINHTML, Area::AREA_CRONTAB], true);
        } catch (LocalizedException) {
            // An unset area does not authorize bypassing the storefront policy.
            return false;
        }
    }
}
