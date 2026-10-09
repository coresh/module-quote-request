<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Adminhtml\Migration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Coresh_QuoteRequest::migrate';
    public function __construct(Context $context, private readonly PageFactory $pages)
    {
        parent::__construct($context);
    }
    public function execute()
    {
        $page = $this->pages->create();
        $page->getConfig()->getTitle()->prepend(__('Legacy Quote Request Migration'));
        return $page;
    }
}
