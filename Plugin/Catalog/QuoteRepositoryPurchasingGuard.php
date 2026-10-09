<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Catalog;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Catalog\QuoteStoreResolver;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

class QuoteRepositoryPurchasingGuard
{
    public function __construct(
        private readonly PurchasingPolicy $policy,
        private readonly QuoteStoreResolver $quoteStoreResolver
    )
    {
    }

    /** Covers standard item/quantity/address/coupon/payment cart mutations through the repository. */
    public function beforeSave(CartRepositoryInterface $subject, CartInterface $quote): void
    {
        $this->policy->assertAllowed((int) $quote->getStoreId());
        $this->policy->assertAllowed($this->quoteStoreResolver->getPersistedStoreId((int) $quote->getId()));
    }
}
