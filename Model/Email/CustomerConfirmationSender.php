<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Email;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

class CustomerConfirmationSender
{
    public function __construct(
        private readonly Config $config,
        private readonly TemplateVariablesBuilder $variables,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $stores
    ) {
    }

    /** Confirmation is optional; failure is handled separately from the admin transport status. */
    public function sendConfirmation(QuoteRequest $request): void
    {
        $storeId = (int) $request->getData('store_id');
        if ($storeId < 1) {
            throw new LocalizedException(__('The original store is unavailable for this request.'));
        }
        $this->stores->getStore($storeId);
        if (!$this->config->isFlag('email/customer_confirmation', $storeId)) {
            return;
        }
        $recipient = (string) $request->getData('customer_email');
        if (strlen($recipient) > 254 || preg_match('/[\r\n]/', $recipient)
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new LocalizedException(__('The confirmation email could not be sent.'));
        }
        $sender = $this->config->getValue('email/sender_identity', $storeId) ?: 'general';
        if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $sender)) {
            throw new LocalizedException(__('The confirmation email could not be sent.'));
        }
        $suspended = false;
        try {
            $this->inlineTranslation->suspend();
            $suspended = true;
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->config->getValue('email/customer_template', $storeId)
                    ?: 'coresh_quote_request_customer_confirmation')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($this->variables->build($request))
                ->setFromByScope($sender, $storeId)
                ->addTo($recipient)
                ->getTransport();
            $transport->sendMessage();
        } finally {
            if ($suspended) {
                $this->inlineTranslation->resume();
            }
        }
    }
}
