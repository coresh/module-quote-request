<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Exception;

use Magento\Framework\Exception\LocalizedException;

/** Safe operational feedback: never expose transport, database or customer exception details. */
class NotificationException extends LocalizedException
{
    private readonly string $errorCode;

    public function __construct(string $errorCode)
    {
        $messages = [
            'original_store_unavailable' => 'The original store is unavailable for this request.',
            'email_attempt_in_progress' => 'An email attempt is already in progress. Check its status before resending.',
            'notification_recipient_invalid' => 'The notification recipient is invalid. Check the recipient email in the original store configuration.',
            'notification_reply_to_invalid' => 'The customer reply-to email is invalid. Check the saved request email address.',
            'notification_sender_invalid' => 'The notification sender could not be configured. Check the sender identity and its email address in the original store configuration.',
            'notification_template_failed' => 'The notification template could not be prepared. Check the selected template and the application log.',
            'notification_transport_failed' => 'The configured email transport could not confirm delivery. Check delivery before resending.',
            'notification_load_failed' => 'The saved request could not be loaded for notification. Check its email status before resending.',
            'notification_lease_failed' => 'The notification attempt could not be started. Check its email status before resending.',
            'notification_status_unconfirmed' => 'The notification was handed to the email transport, but its saved status could not be confirmed. Check delivery before resending.',
            'notification_state_update_failed' => 'The notification attempt failed, but its email status could not be updated. Check its status and delivery before resending.',
            'notification_failed' => 'The notification could not be sent. Check its email status and the application log.'
        ];
        $this->errorCode = isset($messages[$errorCode]) ? $errorCode : 'notification_failed';
        parent::__construct(__($messages[$this->errorCode]));
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
