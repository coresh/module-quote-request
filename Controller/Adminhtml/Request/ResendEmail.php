<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Exception\NotificationException;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Psr\Log\LoggerInterface;

class ResendEmail extends AbstractRequestAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::send_email';

    public function __construct(
        Context $context,
        QuoteRequestFactory $quoteRequestFactory,
        QuoteRequestResource $quoteRequestResource,
        private readonly Validator $formKeyValidator,
        private readonly FormKey $formKey,
        private readonly AdminNotificationSender $notificationSender,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context, $quoteRequestFactory, $quoteRequestResource);
    }

    public function execute()
    {
        $postedKey = $this->getRequest()->getPost('form_key');
        if (!$this->getRequest()->isPost() || !is_string($postedKey)
            || !hash_equals($this->formKey->getFormKey(), $postedKey)
            || !$this->formKeyValidator->validate($this->getRequest())
        ) {
            $this->messageManager->addErrorMessage(__('Invalid form key. Please refresh the page.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        $request = $this->loadRequestEntity();
        if ($request === null) {
            $this->messageManager->addErrorMessage(__('This quote request no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        $requestId = (int) $request->getId();
        try {
            if ($this->notificationSender->sendNotification($request)) {
                $this->messageManager->addSuccessMessage(__('The notification was passed to the email transport.'));
            } else {
                $this->messageManager->addNoticeMessage(__('Notification emails are disabled for this request store.'));
            }
        } catch (NotificationException $exception) {
            $context = ['request_id' => $requestId, 'code' => $exception->getErrorCode()];
            if ($exception->getErrorCode() === 'notification_status_unconfirmed') {
                $this->logFailure('warning', 'Administrative quote request email status is unconfirmed.', $context);
                $this->messageManager->addNoticeMessage($exception->getMessage());
            } else {
                $this->logFailure('error', 'Administrative quote request email failed.', $context);
                $this->messageManager->addErrorMessage($exception->getMessage());
            }
        } catch (\Throwable $exception) {
            $this->logFailure('error', 'Administrative quote request email failed', [
                'request_id' => $requestId, 'code' => 'admin_notification_failed',
                'exception_type' => get_class($exception)
            ]);
            $this->messageManager->addErrorMessage(__('The notification could not be sent. Check its email status.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/view', ['request_id' => $requestId]);
    }

    /** A log backend failure must not erase the safe administrator feedback or its redirect. */
    private function logFailure(string $level, string $message, array $context): void
    {
        try {
            $this->logger->{$level}($message, $context);
        } catch (\Throwable $exception) {
            // Notification delivery and its safe feedback remain authoritative.
        }
    }
}
