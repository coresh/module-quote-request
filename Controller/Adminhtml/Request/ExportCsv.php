<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Ui\Export\RequestCsvExporter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class ExportCsv extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::view';

    public function __construct(
        Context $context,
        private readonly RequestCsvExporter $exporter,
        private readonly FileFactory $fileFactory,
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
        try {
            return $this->fileFactory->create(
                'quote-requests.csv', $this->exporter->getCsvFile(), DirectoryList::VAR_DIR, 'text/csv'
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Quote request CSV export failed', ['code' => 'quote_request_export_failed']);
            $this->messageManager->addErrorMessage(__('The quote requests could not be exported.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
