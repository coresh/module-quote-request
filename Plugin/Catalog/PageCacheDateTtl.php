<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Catalog;

use Coresh\QuoteRequest\Model\Eligibility\DateRangePolicy;
use Magento\PageCache\Model\Config;
use Magento\Store\Model\StoreManagerInterface;

class PageCacheDateTtl
{
    public function __construct(
        private readonly DateRangePolicy $dateRangePolicy,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function afterGetTtl(Config $subject, $result): int
    {
        return $this->dateRangePolicy->clampTtl((int) $this->storeManager->getStore()->getId(), (int) $result);
    }
}
