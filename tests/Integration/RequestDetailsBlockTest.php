<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Coresh\QuoteRequest\Block\Adminhtml\RequestDetails;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Prepared for the real Magento test harness; NOT RUN in the authoring environment.
 * This uses native block ancestry, DI, database persistence, template resolution and Escaper.
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class RequestDetailsBlockTest extends TestCase
{
    private Resource $resource;
    private QuoteRequest $request;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->resource = $om->get(Resource::class);
        $this->request = $om->create(QuoteRequest::class);
        $this->request->setData([
            'store_id' => null, 'customer_id' => null, 'product_id' => null,
            'product_name' => '<script>alert("product")</script>',
            'product_sku' => '<svg onload=alert("sku")>', 'product_type' => 'simple',
            'product_url' => 'https://example.test/product.html', 'quantity' => '2.0000',
            'customer_name' => '<img src=x onerror=alert("customer")>',
            'customer_email' => 'fixture@example.test', 'message' => '<iframe src=javascript:alert("message")>',
            'request_status' => 'new', 'email_status' => 'pending', 'email_attempts' => 0,
            'idempotency_key' => hash('sha256', random_bytes(32)),
            'payload_hash' => hash('sha256', 'request-details-fixture')
        ]);
        $this->resource->save($this->request);
        $om->get(RequestInterface::class)->setParam('request_id', (string) $this->request->getId());
    }

    protected function tearDown(): void
    {
        Bootstrap::getObjectManager()->get(RequestInterface::class)->setParam('request_id', null);
        if (isset($this->request) && $this->request->getId()) {
            $this->resource->delete($this->request);
        }
    }

    private function block(bool $manage, bool $send): RequestDetails
    {
        $om = Bootstrap::getObjectManager();
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(static fn ($resource) => match ($resource) {
            'Coresh_QuoteRequest::manage' => $manage,
            'Coresh_QuoteRequest::send_email' => $send,
            default => false
        });
        /** @var RequestDetails $block */
        $block = $om->create(RequestDetails::class, ['authorization' => $authorization]);
        $block->setLayout($om->get(LayoutInterface::class));
        $block->setTemplate('Coresh_QuoteRequest::request-details.phtml');
        return $block;
    }

    public function testNativeBlockInstantiatesAndUsesCurrentFormKeyForBothPostActions(): void
    {
        $block = $this->block(true, true);
        $token = Bootstrap::getObjectManager()->get(FormKey::class)->getFormKey();
        self::assertSame($token, $block->getFormKey());
        self::assertSame((int) $this->request->getId(), (int) $block->getQuoteRequest()->getId());
        $html = $block->toHtml();
        self::assertSame(2, substr_count($html, 'method="post"'));
        self::assertSame(2, substr_count($html, 'name="form_key" value="' . $token . '"'));
        self::assertSame(2, substr_count($html, 'name="request_id" value="' . $this->request->getId() . '"'));
    }

    public function testNativeTemplateEscapesStoredMarkupAndHonorsSeparateActionPermissions(): void
    {
        $sendOnly = $this->block(false, true);
        self::assertFalse($sendOnly->canManage());
        self::assertTrue($sendOnly->canSendEmail());
        $html = $sendOnly->toHtml();
        self::assertSame(1, substr_count($html, 'method="post"'));
        self::assertStringNotContainsString('name="request_status"', $html);
        self::assertStringContainsString('resendemail', $html);
        foreach (['product_name', 'product_sku', 'customer_name', 'message'] as $field) {
            $value = (string) $this->request->getData($field);
            self::assertStringNotContainsString($value, $html);
            self::assertStringContainsString(htmlspecialchars($value, ENT_COMPAT, 'UTF-8'), $html);
        }
        self::assertStringNotContainsString((string) $this->request->getData('idempotency_key'), $html);
        self::assertStringNotContainsString((string) $this->request->getData('payload_hash'), $html);
        $manageOnly = $this->block(true, false)->toHtml();
        self::assertSame(1, substr_count($manageOnly, 'method="post"'));
        self::assertStringContainsString('name="request_status"', $manageOnly);
        self::assertStringNotContainsString('resendemail', $manageOnly);
        self::assertSame(0, substr_count($this->block(false, false)->toHtml(), 'method="post"'));
    }
}
