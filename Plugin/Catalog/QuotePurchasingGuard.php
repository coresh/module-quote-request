<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Catalog;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Catalog\QuoteStoreResolver;
use Magento\Quote\Model\Quote;

class QuotePurchasingGuard
{
    public function __construct(
        private readonly PurchasingPolicy $policy,
        private readonly QuoteStoreResolver $quoteStoreResolver
    )
    {
    }

    /** Only reads subject; omitted original arguments remain unchanged. */
    public function beforeAddProduct(Quote $subject): void
    {
        $this->policy->assertAllowed((int) $subject->getStoreId());
        $this->policy->assertAllowed($this->quoteStoreResolver->getPersistedStoreId((int) $subject->getId()));
    }
}
