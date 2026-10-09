<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/** Runs only under the real Magento integration harness and its disposable test database. */
class PersistenceTest extends TestCase
{
    public function testDeclarativeTablesHaveRequiredIndependentFields(): void
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $columns = $resource->getConnection()->describeTable($resource->getTableName('coresh_quote_request'));
        foreach (['request_id', 'store_id', 'product_name', 'product_id', 'quantity', 'request_status', 'email_status',
            'email_attempts', 'email_lease_started_at', 'idempotency_key', 'quantity_origin', 'legacy_id'] as $column) {
            self::assertArrayHasKey($column, $columns);
        }
        self::assertTrue($resource->getConnection()->isTableExists($resource->getTableName('coresh_quote_request_rate_limit')));
    }

    /** @magentoDbIsolation enabled */
    public function testSnapshotAndExactQuantityRoundTripWithoutSendingEmail(): void
    {
        $om = Bootstrap::getObjectManager();
        $resource = $om->get(Resource::class);
        $request = $om->create(QuoteRequest::class);
        $request->setData(['store_id' => null, 'customer_id' => null, 'product_id' => null,
            'product_name' => 'Archived product snapshot', 'product_sku' => 'INTEGRATION-42',
            'product_type' => 'simple', 'product_url' => 'https://example.test/product.html', 'quantity' => '2.5000',
            'customer_name' => 'Test customer', 'customer_email' => 'fixture@example.test',
            'message' => '</textarea>{{var arbitrary_directive}}', 'request_status' => 'new',
            'email_status' => 'pending', 'idempotency_key' => hash('sha256', random_bytes(32))]);
        $resource->save($request);
        try {
            $loaded = $om->create(QuoteRequest::class);
            $resource->load($loaded, $request->getId());
            self::assertSame('2.5000', (string) $loaded->getData('quantity'));
            self::assertSame('Archived product snapshot', $loaded->getData('product_name'));
            self::assertSame('new', $loaded->getData('request_status'));
            self::assertSame('pending', $loaded->getData('email_status'));
            self::assertSame('</textarea>{{var arbitrary_directive}}', $loaded->getData('message'));
        } finally {
            $resource->delete($request);
        }
    }
}
