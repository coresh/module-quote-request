<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Request;

use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

abstract class AbstractRequestAction extends Action
{
    public function __construct(
        Context $context,
        protected readonly QuoteRequestFactory $quoteRequestFactory,
        protected readonly QuoteRequestResource $quoteRequestResource
    ) {
        parent::__construct($context);
    }

    protected function loadRequestEntity(): ?QuoteRequest
    {
        $id = $this->getRequest()->getParam('request_id');
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) {
            return null;
        }
        $request = $this->quoteRequestFactory->create();
        $this->quoteRequestResource->load($request, (int) $id);
        return $request->getId() ? $request : null;
    }
}
