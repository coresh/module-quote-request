<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Real backend ACL/controller assets, NOT RUN without an installed Magento test harness.
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class MassStatusAclTest extends AbstractBackendController
{
    protected $resource = 'Coresh_QuoteRequest::manage';
    protected $uri = 'backend/coresh_quoterequest/request/massstatus';
    protected $httpMethod = 'POST';

    public function testFormKeyOnlyInQueryCannotExecuteMassStatus(): void
    {
        $this->getRequest()->setMethod('POST')->setPostValue([]);
        $this->getRequest()->setParam('form_key', $this->_objectManager->get(FormKey::class)->getFormKey());
        $this->getRequest()->setParam('namespace', 'coresh_quote_request_listing');
        $this->getRequest()->setParam('excluded', 'false');
        $this->getRequest()->setParam('status', 'closed');
        $this->dispatch($this->uri);
        self::assertGreaterThanOrEqual(300, $this->getResponse()->getHttpResponseCode());
        self::assertLessThan(400, $this->getResponse()->getHttpResponseCode());
    }
}
