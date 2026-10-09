<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Eligibility;

use Coresh\QuoteRequest\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context;

class QuoteRequestEligibility
{
    public function __construct(
        private readonly Config $config,
        private readonly DateRangePolicy $dateRangePolicy,
        private readonly CategoryRuleResolver $categoryRules,
        private readonly Context $httpContext
    ) {
    }

    public function canRequestQuote(ProductInterface $product, int $storeId, ?int $groupId = null): bool
    {
        if (!$this->config->isEnabled($storeId)
            || !$this->isCustomerGroupAllowed($storeId, $groupId)
            || !$this->dateRangePolicy->isAllowed($storeId)
        ) {
            return false;
        }
        if ($this->config->isFlag('general/apply_to_all_products', $storeId)
            || $this->config->getValue('general/scope', $storeId) === 'global'
        ) {
            return true;
        }
        $flag = $product->getCustomAttribute('coresh_quote_request_enabled');
        if ($flag !== null && (bool) $flag->getValue()) {
            return true;
        }
        $selected = $this->config->getValue('general/scope', $storeId) === 'category'
            ? $this->parseIds($this->config->getValue('general/selected_categories', $storeId)) : [];
        return $this->categoryRules->matches($this->getCategoryIds($product), $storeId, $selected);
    }

    public function isCustomerGroupAllowed(int $storeId, ?int $groupId = null): bool
    {
        if (!$this->config->isFlag('general/restrict_customer_groups', $storeId)) {
            return true;
        }
        $allowed = trim($this->config->getValue('general/customer_groups', $storeId));
        if ($allowed === '') {
            return false;
        }
        $groupId ??= (int) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
        return in_array($groupId, $this->parseIds($allowed), true);
    }

    private function parseIds(string $values): array
    {
        $ids = [];
        foreach (explode(',', $values) as $value) {
            $value = trim($value);
            if ($value !== '' && ctype_digit($value)) {
                $ids[] = (int) $value;
            }
        }
        return array_values(array_unique($ids));
    }

    private function getCategoryIds(ProductInterface $product): array
    {
        if (method_exists($product, 'getCategoryIds')) {
            return array_map('intval', (array) $product->getCategoryIds());
        }
        $links = $product->getExtensionAttributes()?->getCategoryLinks() ?? [];
        return array_map(static fn ($link): int => (int) $link->getCategoryId(), $links);
    }
}
