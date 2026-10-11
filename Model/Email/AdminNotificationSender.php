<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Email;

use Coresh\QuoteRequest\Exception\NotificationException;
use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilderFactory;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AdminNotificationSender
{
    public function __construct(
        private readonly Config $config,
        private readonly QuoteRequestResource $resource,
        private readonly TemplateVariablesBuilder $variables,
        private readonly TransportBuilderFactory $transportBuilderFactory,
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
            throw new NotificationException('original_store_unavailable');
        }
        try {
            $this->stores->getStore($storeId);
        } catch (\Throwable $exception) {
            throw new NotificationException('original_store_unavailable');
        }
        $attempt = null;
        $phase = 'notification_failed';
        $handedOff = false;
        $translationDisabled = false;
        try {
            // Resending a saved request does not depend on current storefront/product eligibility.
            if (!$this->config->isFlag('email/enabled', $storeId)) {
                return false;
            }
            $phase = 'notification_lease_failed';
            $claimedAttempt = $this->resource->claimEmailAttempt($requestId);
            if ($claimedAttempt === false) {
                throw new NotificationException('email_attempt_in_progress');
            }
            $attempt = $claimedAttempt;
            $phase = 'notification_load_failed';
            $this->resource->load($request, $requestId);
            if ((int) $request->getId() !== $requestId || (int) $request->getData('store_id') !== $storeId) {
                throw new NotificationException('notification_load_failed');
            }
            $phase = 'notification_recipient_invalid';
            $recipient = $this->config->getRecipient($storeId);
            if (!$this->isValidAddress($recipient)) {
                throw new NotificationException($phase);
            }
            $phase = 'notification_reply_to_invalid';
            $replyTo = (string) $request->getData('customer_email');
            if (!$this->isValidAddress($replyTo)) {
                throw new NotificationException($phase);
            }
            $phase = 'notification_sender_invalid';
            $sender = $this->config->getValue('email/sender_identity', $storeId) ?: 'general';
            if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $sender)) {
                throw new NotificationException($phase);
            }
            $phase = 'notification_template_failed';
            $template = $this->config->getValue('email/admin_template', $storeId)
                ?: 'coresh_quote_request_admin_notification';
            // Do not alter an outer suspension's saved state. Magento template emulation owns it.
            if ($this->inlineTranslation->isEnabled()) {
                $translationDisabled = true;
                $this->inlineTranslation->disable();
            }
            // A failed fluent setup does not reset Magento's builder. Never reuse it across emails.
            $builder = $this->transportBuilderFactory->create();
            $builder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($this->variables->build($request));
            $phase = 'notification_sender_invalid';
            $builder->setFromByScope($sender, $storeId);
            $phase = 'notification_recipient_invalid';
            $builder->addTo($recipient);
            $phase = 'notification_reply_to_invalid';
            $builder->setReplyTo($replyTo);
            $phase = 'notification_template_failed';
            $transport = $builder->getTransport();
            $phase = 'notification_transport_failed';
            $transport->sendMessage();
            $handedOff = true;
            $phase = 'notification_status_unconfirmed';
            $this->resource->completeEmailAttempt($requestId, 'sent', null, $attempt);
            $this->resource->load($request, $requestId);
            // Completion may affect no row when an expired lease was replaced or the row disappeared.
            if ((int) $request->getId() !== $requestId
                || (int) $request->getData('store_id') !== $storeId
                || $request->getData('email_status') !== 'sent'
                || (int) $request->getData('email_attempts') !== $attempt
            ) {
                throw new NotificationException('notification_status_unconfirmed');
            }
            return true;
        } catch (\Throwable $exception) {
            $errorCode = $exception instanceof NotificationException ? $exception->getErrorCode() : $phase;
            if ($handedOff) {
                // Delivery may already have occurred. A bookkeeping failure must never mark it failed.
                $errorCode = 'notification_status_unconfirmed';
            } elseif ($attempt !== null) {
                try {
                    $this->resource->completeEmailAttempt($requestId, 'failed', $errorCode, $attempt);
                } catch (\Throwable $stateException) {
                    $errorCode = 'notification_state_update_failed';
                    $this->log('error', 'Quote request notification state update failed.', [
                        'request_id' => $requestId,
                        'error_code' => $errorCode,
                        'exception_type' => get_class($stateException)
                    ]);
                }
                try {
                    $this->resource->load($request, $requestId);
                } catch (\Throwable $reloadException) {
                    $this->log('warning', 'Quote request notification state reload failed.', [
                        'request_id' => $requestId,
                        'error_code' => $errorCode,
                        'exception_type' => get_class($reloadException)
                    ]);
                }
            }
            $this->log('error', 'Quote request notification failed.', [
                'request_id' => $requestId,
                'error_code' => $errorCode,
                'exception_type' => get_class($exception)
            ]);
            throw new NotificationException($errorCode);
        } finally {
            if ($translationDisabled) {
                $this->restoreTranslation($requestId);
            }
        }
    }

    private function restoreTranslation(int $requestId): void
    {
        try {
            // Restore the exact initial enabled flag without touching emulation's stored status.
            $this->inlineTranslation->enable();
        } catch (\Throwable $exception) {
            $this->log('warning', 'Quote request inline translation cleanup failed.', [
                'request_id' => $requestId,
                'error_code' => 'translation_cleanup_failed',
                'exception_type' => get_class($exception)
            ]);
        }
    }

    /** Logging failures must not replace the notification outcome or expose raw exception details. */
    private function log(string $level, string $message, array $context): void
    {
        try {
            $this->logger->{$level}($message, $context);
        } catch (\Throwable $exception) {
            // The safe notification outcome remains authoritative when the log backend is unavailable.
        }
    }

    private function isValidAddress(string $address): bool
    {
        return strlen($address) <= 254 && !preg_match('/[\r\n]/', $address)
            && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }
}
