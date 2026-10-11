<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Email;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AdminNotificationSender
{
    public function __construct(
        private readonly Config $config,
        private readonly QuoteRequestResource $resource,
        private readonly TemplateVariablesBuilder $variables,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $stores,
        private readonly LoggerInterface $logger
    ) {
    }

    /** False means sending is disabled; true means handoff to the configured transport. */
    public function sendNotification(QuoteRequest $request): bool
    {
        $requestId = (int) $request->getId();
        $storeId = (int) $request->getData('store_id');
        if ($requestId < 1 || $storeId < 1) {
            throw new LocalizedException(__('The original store is unavailable for this request.'));
        }
        try {
            $this->stores->getStore($storeId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $exception) {
            throw new LocalizedException(__('The original store is unavailable for this request.'));
        }
        // Resend of an historical request ignores current product eligibility and frontend enabled state.
        if (!$this->config->isFlag('email/enabled', $storeId)) {
            return false;
        }
        $attempt = $this->resource->claimEmailAttempt($requestId);
        if ($attempt === false) {
            throw new LocalizedException(__('An email attempt is already in progress. Please try again later.'));
        }
        $this->resource->load($request, $requestId);
        $suspended = false;
        try {
            $recipient = $this->config->getRecipient($storeId);
            $replyTo = (string) $request->getData('customer_email');
            if (!$this->isValidAddress($recipient) || !$this->isValidAddress($replyTo)) {
                throw new \UnexpectedValueException('Invalid configured email address.');
            }
            $sender = $this->config->getValue('email/sender_identity', $storeId) ?: 'general';
            if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $sender)) {
                throw new \UnexpectedValueException('Invalid configured sender identity.');
            }
            $template = $this->config->getValue('email/admin_template', $storeId)
                ?: 'coresh_quote_request_admin_notification';
            $this->inlineTranslation->suspend();
            $suspended = true;
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($this->variables->build($request))
                ->setFromByScope($sender, $storeId)
                ->addTo($recipient)
                ->addReplyTo($replyTo)
                ->getTransport();
            $transport->sendMessage();
            $this->resource->completeEmailAttempt($requestId, 'sent', null, $attempt);
            $this->resource->load($request, $requestId);
            return true;
        } catch (\Throwable $exception) {
            $this->resource->completeEmailAttempt($requestId, 'failed', 'notification_failed', $attempt);
            $this->resource->load($request, $requestId);
            $this->logger->error('Quote request email transport failed.', [
                'request_id' => $requestId,
                'error_code' => 'notification_failed'
            ]);
            throw new LocalizedException(__('The request is saved, but its email could not be sent. Please try again later.'));
        } finally {
            if ($suspended) {
                $this->inlineTranslation->resume();
            }
        }
    }

    private function isValidAddress(string $address): bool
    {
        return strlen($address) <= 254 && !preg_match('/[\r\n]/', $address)
            && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }
}
