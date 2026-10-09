<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest;
use Coresh\QuoteRequest\Ui\Service\SelectedRequests;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::manage';

    public function __construct(
        Context $context,
        private readonly SelectedRequests $selection,
        private readonly QuoteRequest $resource,
        private readonly Validator $formKeyValidator,
        private readonly FormKey $formKey,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
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
        $status = $this->getRequest()->getParam('status');
        if (!is_string($status) || !in_array($status, ['new', 'processing', 'closed'], true)) {
            $this->messageManager->addErrorMessage(__('Please select a valid request status.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        $changed = 0;
        try {
            $batch = [];
            foreach ($this->selection->rows() as $row) {
                $batch[] = (int) $row['request_id'];
                if (count($batch) === 500) {
                    $changed += $this->resource->updateRequestStatuses($batch, $status);
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $changed += $this->resource->updateRequestStatuses($batch, $status);
            }
            $this->messageManager->addSuccessMessage(__('%1 quote requests updated.', $changed));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Bulk quote request status update failed', [
                'code' => 'quote_request_mass_status_failed', 'changed_count' => $changed
            ]);
            $this->messageManager->addErrorMessage(__('The status update could not finish. Refresh the listing to check its result.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
