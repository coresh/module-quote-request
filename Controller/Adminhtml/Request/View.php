<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class View extends AbstractRequestAction implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::view';

    public function __construct(
        Context $context,
        QuoteRequestFactory $quoteRequestFactory,
        QuoteRequestResource $quoteRequestResource,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context, $quoteRequestFactory, $quoteRequestResource);
    }

    public function execute()
    {
        $request = $this->loadRequestEntity();
        if ($request === null) {
            $this->messageManager->addErrorMessage(__('This quote request no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Coresh_QuoteRequest::requests');
        $page->getConfig()->getTitle()->prepend(__('Quote Request #%1', (int) $request->getId()));
        return $page;
    }
}
