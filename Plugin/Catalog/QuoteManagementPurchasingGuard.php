<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Catalog;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Catalog\QuoteStoreResolver;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

class QuoteManagementPurchasingGuard
{
    public function __construct(
        private readonly PurchasingPolicy $policy,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly QuoteStoreResolver $quoteStoreResolver
    ) {
    }

    public function beforePlaceOrder(QuoteManagement $subject, $cartId): void
    {
        $this->policy->assertAllowed();
        $this->policy->assertAllowed($this->quoteStoreResolver->getPersistedStoreId((int) $cartId));
        $quote = $this->quoteRepository->getActive($cartId);
        $this->policy->assertAllowed((int) $quote->getStoreId());
    }

    /** Also protects standard multishipping and direct core submit users. */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote): void
    {
        $this->policy->assertAllowed((int) $quote->getStoreId());
        $this->policy->assertAllowed($this->quoteStoreResolver->getPersistedStoreId((int) $quote->getId()));
    }
}
