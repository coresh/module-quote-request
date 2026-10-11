<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Email;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilderFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CustomerConfirmationSender
{
    public function __construct(
        private readonly Config $config,
        private readonly TemplateVariablesBuilder $variables,
        private readonly TransportBuilderFactory $transportBuilderFactory,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $stores,
        private readonly LoggerInterface $logger
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
        $translationDisabled = false;
        try {
            if ($this->inlineTranslation->isEnabled()) {
                $translationDisabled = true;
                $this->inlineTranslation->disable();
            }
            $transport = $this->transportBuilderFactory->create()
                ->setTemplateIdentifier($this->config->getValue('email/customer_template', $storeId)
                    ?: 'coresh_quote_request_customer_confirmation')
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($this->variables->build($request))
                ->setFromByScope($sender, $storeId)
                ->addTo($recipient)
                ->getTransport();
            $transport->sendMessage();
        } finally {
            if ($translationDisabled) {
                $this->restoreTranslation((int) $request->getId());
            }
        }
    }

    private function restoreTranslation(int $requestId): void
    {
        try {
            $this->inlineTranslation->enable();
        } catch (\Throwable $exception) {
            $this->log('Quote request confirmation translation cleanup failed.', [
                'request_id' => $requestId,
                'error_code' => 'translation_cleanup_failed',
                'exception_type' => get_class($exception)
            ]);
        }
    }

    private function log(string $message, array $context): void
    {
        try {
            $this->logger->warning($message, $context);
        } catch (\Throwable $exception) {
            // Do not replace a transport result with a cleanup logging failure.
        }
    }
}
