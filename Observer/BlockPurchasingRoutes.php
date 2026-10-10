<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Observer;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

/** Early storefront denial complements server guards; no cart or payment settings are deleted. */
class BlockPurchasingRoutes implements ObserverInterface
{
    public function __construct(
        private readonly PurchasingPolicy $policy,
        private readonly CheckoutSession $checkoutSession,
        private readonly ActionFlag $actionFlag,
        private readonly RedirectInterface $redirect,
        private readonly UrlInterface $url,
        private readonly ManagerInterface $messageManager,
        private readonly Http $request,
        private readonly HttpResponse $response
    ) {
    }

    public function execute(Observer $observer): void
    {
        // Modern ActionInterface controllers need not expose request/response getters.
        $eventRequest = $observer->getEvent()->getRequest();
        $request = $eventRequest instanceof Http ? $eventRequest : $this->request;
        $route = (string) $request->getRouteName();
        $action = (string) $request->getFullActionName();
        $blockCheckout = in_array($route, ['checkout', 'multishipping', 'paypal'], true);
        // Success/past order views stay accessible. No new cart mutation is permitted in catalogue mode.
        if (!$blockCheckout || in_array($action, ['checkout_onepage_success', 'checkout_onepage_failure'], true)) {
            return;
        }
        try {
            $this->policy->assertAllowed();
            // Existing quote context prevents a store switch from bypassing storefront route checks.
            $quoteId = $this->checkoutSession->getQuoteId();
            if ($quoteId) {
                $this->policy->assertAllowed((int) $this->checkoutSession->getQuote()->getStoreId());
            }
        } catch (LocalizedException $exception) {
            $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
            $this->response->setNoCacheHeaders();
            if ($request->isXmlHttpRequest()) {
                $this->response->setHttpResponseCode(403);
                $this->response->setHeader('Content-Type', 'application/json', true);
                $this->response->setBody('{"success":false,"message":"Online purchasing is unavailable. Please request a quote."}');
                return;
            }
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->redirect->redirect($this->response, $this->url->getUrl(''));
        }
    }
}
