<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Prepared for the real Magento harness; NOT RUN in the authoring environment.
 * Inherited testAclHasAccess/testAclNoAccess use Magento's authenticated admin and ACL builder.
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ExportAclTest extends AbstractBackendController
{
    protected $resource = 'Coresh_QuoteRequest::view';
    protected $uri = 'backend/coresh_quoterequest/request/exportcsv';
    protected $httpMethod = 'POST';

    public function testFormKeyOnlyInQueryDoesNotProduceCsv(): void
    {
        $this->getRequest()->setMethod('POST')->setPostValue([]);
        $this->getRequest()->setParam('form_key', $this->_objectManager->get(FormKey::class)->getFormKey());
        $this->getRequest()->setParam('namespace', 'coresh_quote_request_listing');
        $this->getRequest()->setParam('excluded', 'false');
        $this->dispatch($this->uri);
        self::assertGreaterThanOrEqual(300, $this->getResponse()->getHttpResponseCode());
        self::assertLessThan(400, $this->getResponse()->getHttpResponseCode());
        self::assertEmpty($this->getResponse()->getHeader('Content-Disposition'));
    }
}
