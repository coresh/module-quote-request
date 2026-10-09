<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Component\Listing;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\MassAction;

class RequestMassAction extends MassAction
{
    public function __construct(
        ContextInterface $context,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    public function prepare()
    {
        if (!$this->authorization->isAllowed('Coresh_QuoteRequest::manage')) {
            $config = $this->getConfiguration();
            $config['actions'] = [];
            $config['visible'] = false;
            $this->setData('config', $config);
            $this->components = [];
        }
        parent::prepare();
    }
}
