<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Migration;

use Coresh\QuoteRequest\Model\Migration\LegacyDataMigrator;
use Coresh\QuoteRequest\Model\Migration\LegacySourceDetector;
use Coresh\QuoteRequest\Model\Migration\MigrationApproval;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Psr\Log\LoggerInterface;

class Run extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::migrate';
    public function __construct(
        Context $context,
        private readonly LegacyDataMigrator $migrator,
        private readonly LegacySourceDetector $detector,
        private readonly MigrationApproval $approval,
        private readonly Session $session,
        private readonly FormKey $formKey,
        private readonly Validator $validator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $request = $this->getRequest();
        $key = $request->getPost('form_key');
        $storeInput = $request->getPost('store_id');
        $mode = $request->getPost('mode');
        if (!$request->isPost() || !is_string($key) || !hash_equals($this->formKey->getFormKey(), $key)
            || !$this->validator->validate($request)
            || !is_string($storeInput) || preg_match('/\A[1-9][0-9]{0,4}\z/', $storeInput) !== 1
            || !in_array($mode, ['preview', 'execute'], true)
        ) {
            $this->messageManager->addErrorMessage(__('Invalid migration request. Refresh the page.'));
            return $redirect;
        }
        $storeId = (int) $storeInput;
        try {
            if (!$this->detector->hasSupportedSource()) {
                $this->messageManager->addErrorMessage(__('A verified legacy source is required.'));
                return $redirect;
            }
            if ($mode === 'preview') {
                $this->session->unsetData('coresh_quote_request_migration_approval');
                $this->session->unsetData('coresh_quote_request_migration_preview');
                $report = $this->migrator->migrate($storeId, false);
                $prepared = $this->approval->create($storeId);
                $this->session->setData('coresh_quote_request_migration_approval', $prepared['state']);
                $this->session->setData('coresh_quote_request_migration_preview', [
                    'store_id' => $storeId, 'token' => $prepared['token'], 'report' => $report
                ]);
                $this->messageManager->addSuccessMessage(__('Preview complete. No data was written and no email was sent.'));
                return $redirect;
            }
            if ($request->getPost('confirmed') !== '1' || !$this->approval->validate(
                $request->getPost('preview_token'), $storeId,
                $this->session->getData('coresh_quote_request_migration_approval')
            )) {
                $this->messageManager->addErrorMessage(__('Preview this store first and confirm the import. Preview expires after 10 minutes.'));
                return $redirect;
            }
            // Consume session approval before side effects; database uniqueness also preserves repeated imports.
            $this->session->unsetData('coresh_quote_request_migration_approval');
            $this->session->unsetData('coresh_quote_request_migration_preview');
            $report = $this->migrator->migrate($storeId, true);
            $this->session->setData('coresh_quote_request_migration_preview', ['store_id' => $storeId, 'report' => $report]);
            $this->messageManager->addSuccessMessage(__('Import complete. No source data was deleted and no email was sent. Clean catalogue/configuration caches and reindex product attributes.'));
        } catch (\Throwable) {
            $this->logger->error('Legacy quote migration failed.', ['error_code' => 'legacy_import_failed']);
            $this->messageManager->addErrorMessage(__('Migration failed. Source data was not deleted; successful partial imports can be previewed and resumed.'));
        }
        return $redirect;
    }
}
