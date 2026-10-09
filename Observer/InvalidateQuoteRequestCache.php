<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Observer;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
/** Native cache-tag event purges Varnish; built-in page and block caches also expire after rule changes. */
class InvalidateQuoteRequestCache implements ObserverInterface, IdentityInterface
{
    public function __construct(
        private readonly TypeListInterface $cacheTypes,
        private readonly ManagerInterface $eventManager
    ) {
    }
    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getCategory();
        if ($category !== null && !$category->dataHasChangedFor('coresh_quote_request_enabled')) {
            return;
        }
        $this->cacheTypes->cleanType('block_html');
        $this->cacheTypes->cleanType('full_page');
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $this]);
    }
    public function getIdentities(): array
    {
        return ['coresh_quote_request'];
    }
}
