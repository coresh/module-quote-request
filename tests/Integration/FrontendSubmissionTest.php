<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Integration;

use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as RequestResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Native dispatch/predispatch/DI/persistence asset; NOT RUN in the preparation environment.
 * Email is disabled by fixtures: this asset never sends external messages.
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/product_simple.php
 * @magentoConfigFixture default_store coresh_quote_request/general/enabled 1
 * @magentoConfigFixture default_store coresh_quote_request/general/scope global
 * @magentoConfigFixture default_store coresh_quote_request/general/restrict_customer_groups 0
 * @magentoConfigFixture default_store coresh_quote_request/general/use_date_range 0
 * @magentoConfigFixture default_store coresh_quote_request/general/request_display button
 * @magentoConfigFixture default_store coresh_quote_request/email/enabled 0
 * @magentoConfigFixture default_store coresh_quote_request/email/customer_confirmation 0
 * @magentoConfigFixture default_store coresh_quote_request/security/rate_limit_enabled 0
 * @magentoConfigFixture default_store coresh_quote_request/catalog/disable_purchasing 1
 */
class FrontendSubmissionTest extends AbstractController
{
    private function payload(): array
    {
        $product = $this->_objectManager->get(ProductRepositoryInterface::class)->get('simple');
        return [
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'request_token' => bin2hex(random_bytes(32)), 'product_id' => (string) $product->getId(),
            'quantity' => '2', 'customer_name' => 'Integration requester',
            'customer_email' => 'integration@example.test', 'customer_telephone' => '',
            'message' => 'Please provide a quote.', 'honeypot' => '',
            'product_name' => 'Untrusted client name', 'product_sku' => 'UNTRUSTED-SKU',
            'store_id' => '999', 'customer_id' => '999', 'request_status' => 'closed'
        ];
    }

    private function submit(array $payload): array
    {
        $this->getResponse()->clearBody();
        $this->getRequest()->setMethod('POST')->setPostValue($payload);
        $this->getRequest()->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('quoterequest/request/submit');
        return json_decode($this->getResponse()->getBody(), true, 32, JSON_THROW_ON_ERROR);
    }

    private function rowCount(string $token): int
    {
        $resource = $this->_objectManager->get(ResourceConnection::class);
        return (int) $resource->getConnection()->fetchOne(
            $resource->getConnection()->select()->from($resource->getTableName('coresh_quote_request'), ['COUNT(*)'])
                ->where('idempotency_key = ?', hash('sha256', $token))
        );
    }

    public function testValidPopupPostPersistsTrustedSnapshotWhilePurchasingIsDisabled(): void
    {
        $payload = $this->payload();
        $response = $this->submit($payload);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertTrue($response['success']);
        self::assertSame('accepted', $response['status']);
        self::assertGreaterThan(0, $response['request_id']);
        self::assertSame(1, $this->rowCount($payload['request_token']));
        $request = $this->_objectManager->create(QuoteRequest::class);
        $this->_objectManager->get(RequestResource::class)->load($request, $response['request_id']);
        $product = $this->_objectManager->get(ProductRepositoryInterface::class)->get('simple');
        self::assertSame((string) $product->getName(), $request->getData('product_name'));
        self::assertSame((string) $product->getSku(), $request->getData('product_sku'));
        self::assertSame((int) $product->getId(), (int) $request->getData('product_id'));
        self::assertSame('2.0000', $request->getData('quantity'));
        self::assertSame('new', $request->getData('request_status'));
        self::assertSame('disabled', $request->getData('email_status'));
        self::assertStringNotContainsString($payload['customer_email'], $this->getResponse()->getBody());
    }

    public function testIdenticalRetryAcknowledgesTheSameSavedRequest(): void
    {
        $payload = $this->payload();
        $first = $this->submit($payload);
        $second = $this->submit($payload);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertTrue($second['success']);
        self::assertSame('duplicate', $second['status']);
        self::assertSame($first['request_id'], $second['request_id']);
        self::assertSame(1, $this->rowCount($payload['request_token']));
    }

    public function testChangedPayloadCannotReuseAnAcceptedToken(): void
    {
        $payload = $this->payload();
        self::assertTrue($this->submit($payload)['success']);
        $payload['message'] = 'Changed request without a fresh token.';
        $response = $this->submit($payload);
        self::assertSame(400, $this->getResponse()->getHttpResponseCode());
        self::assertFalse($response['success']);
        self::assertArrayNotHasKey('request_id', $response);
        self::assertStringContainsString('refresh the page', $response['message']);
        self::assertSame(1, $this->rowCount($payload['request_token']));
    }
}
