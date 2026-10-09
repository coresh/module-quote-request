<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AddProductListAttribute implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $collection = $observer->getEvent()->getCollection();
        if ($collection !== null) {
            $collection->addAttributeToSelect(['coresh_quote_request_enabled', 'coresh_quote_request_label_mode', 'coresh_quote_request_button_label', 'coresh_quote_request_allow_cart', 'coresh_quote_request_show_price']);
        }
    }
}
