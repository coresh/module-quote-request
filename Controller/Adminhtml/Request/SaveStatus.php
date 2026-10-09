<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Psr\Log\LoggerInterface;

class SaveStatus extends AbstractRequestAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::manage';

    public function __construct(
        Context $context,
        QuoteRequestFactory $quoteRequestFactory,
        QuoteRequestResource $quoteRequestResource,
        private readonly Validator $formKeyValidator,
        private readonly FormKey $formKey,
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
        $status = $this->getRequest()->getPost('request_status');
        if (!is_string($status) || !in_array($status, ['new', 'processing', 'closed'], true)) {
            $this->messageManager->addErrorMessage(__('Please select a valid request status.'));
        } else {
            try {
                $this->quoteRequestResource->updateRequestStatus((int) $request->getId(), $status);
                $this->messageManager->addSuccessMessage(__('The request status has been saved.'));
            } catch (\Throwable $exception) {
                $this->logger->error('Quote request status update failed', [
                    'request_id' => (int) $request->getId(), 'code' => 'admin_status_update_failed'
                ]);
                $this->messageManager->addErrorMessage(__('The request status could not be saved.'));
            }
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/view', ['request_id' => $request->getId()]);
    }
}
