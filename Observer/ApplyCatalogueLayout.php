<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Observer;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class ApplyCatalogueLayout implements ObserverInterface
{
    public function __construct(private readonly Config $config, private readonly StoreManagerInterface $storeManager)
    {
    }

    public function execute(Observer $observer): void
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $layout = $observer->getEvent()->getLayout();
        if ($layout === null) {
            return;
        }
        if ($this->config->isCartHidden($storeId)) {
            foreach (['minicart', 'checkout.cart.link', 'checkout_cart_link', 'top.link.cart', 'cart_sidebar'] as $name) {
                if ($layout->getBlock($name)) {
                    $layout->unsetElement($name);
                }
            }
        }
        if ($this->config->isPurchasingDisabled($storeId)) {
            foreach (['product.info.addtocart', 'product.info.addtocart.additional', 'checkout.onepage.link',
                'checkout.multishipping.link', 'checkout.cart.methods', 'checkout.cart.methods.bottom',
                'checkout.cart.shortcut.buttons', 'checkout.cart.extra', 'product.info.addtocart.paypal'] as $name) {
                if ($layout->getBlock($name)) {
                    $layout->unsetElement($name);
                }
            }
        }
    }
}
