<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Database tests require real Magento resource models and MySQL test transactions.
 * @magentoDbIsolation enabled
 */
class AtomicResourceTest extends TestCase
{
    private Resource $resource;
    private QuoteRequest $request;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->resource = $om->get(Resource::class);
        $this->request = $om->create(QuoteRequest::class);
        $this->request->setData(['store_id' => null, 'customer_id' => null, 'product_id' => null,
            'product_name' => 'Atomic snapshot', 'product_sku' => 'ATOMIC-42', 'product_type' => 'simple',
            'product_url' => 'https://example.test/product.html', 'quantity' => '3.0000',
            'customer_name' => 'Fixture', 'customer_email' => 'fixture@example.test', 'message' => 'Quote enquiry',
            'request_status' => 'new', 'email_status' => 'pending', 'email_attempts' => 0,
            'idempotency_key' => hash('sha256', random_bytes(32)), 'payload_hash' => hash('sha256', 'fixture')]);
        $this->resource->save($this->request);
    }

    protected function tearDown(): void
    {
        if (isset($this->request) && $this->request->getId()) {
            $this->resource->delete($this->request);
        }
    }

    private function row(): array
    {
        return $this->resource->getConnection()->fetchRow($this->resource->getConnection()->select()
            ->from($this->resource->getMainTable())->where('request_id = ?', (int) $this->request->getId()));
    }

    public function testUniqueIdempotencyKeyRejectsSecondInsert(): void
    {
        $stored = $this->row();
        self::assertSame((int) $this->request->getId(), (int) $this->resource
            ->findByIdempotencyKey((string) $this->request->getData('idempotency_key'))['request_id']);
        unset($stored['request_id']);
        $this->expectException(DuplicateException::class);
        $this->resource->getConnection()->insert($this->resource->getMainTable(), $stored);
    }

    public function testSendLeaseFailedTransportAndStatusUpdatesPreserveSnapshot(): void
    {
        $id = (int) $this->request->getId();
        $attempt = $this->resource->claimEmailAttempt($id);
        self::assertSame(1, $attempt);
        self::assertFalse($this->resource->claimEmailAttempt($id));
        $startedAt = $this->row()['email_lease_started_at'];
        self::assertNotEmpty($startedAt);
        $this->resource->updateRequestStatus($id, 'processing');
        self::assertSame('sending', $this->row()['email_status']);
        self::assertSame(1, (int) $this->row()['email_attempts']);
        self::assertSame($startedAt, $this->row()['email_lease_started_at']);
        $this->resource->completeEmailAttempt($id, 'failed', 'transport_failed', $attempt);
        $row = $this->row();
        self::assertSame('processing', $row['request_status']);
        self::assertSame('failed', $row['email_status']);
        self::assertSame('transport_failed', $row['last_error_code']);
        self::assertNull($row['email_lease_started_at']);
        self::assertSame('Atomic snapshot', $row['product_name']);
        self::assertSame('3.0000', (string) $row['quantity']);
        self::assertSame('Quote enquiry', $row['message']);
        self::assertSame(2, $this->resource->claimEmailAttempt($id));
    }

    public function testExpiredLeaseCompareAndSwapRejectsLateFirstWorkerCompletion(): void
    {
        $id = (int) $this->request->getId();
        self::assertSame(1, $this->resource->claimEmailAttempt($id));
        $this->resource->getConnection()->update($this->resource->getMainTable(),
            ['email_lease_started_at' => gmdate('Y-m-d H:i:s', time() - 601)], ['request_id = ?' => $id]);
        // A recent admin status edit updates updated_at but must not prolong an expired email lease.
        $this->resource->updateRequestStatus($id, 'processing');
        self::assertSame(2, $this->resource->claimEmailAttempt($id));
        $this->resource->completeEmailAttempt($id, 'failed', 'late_first_worker', 1);
        self::assertSame('sending', $this->row()['email_status']);
        self::assertNull($this->row()['last_error_code']);
        $this->resource->completeEmailAttempt($id, 'sent', null, 2);
        self::assertSame('sent', $this->row()['email_status']);
        self::assertNotEmpty($this->row()['last_email_sent_at']);
        self::assertNull($this->row()['email_lease_started_at']);
    }

    public function testMassStatusOnlyChangesHandlingColumnsAndPreservesEmailLease(): void
    {
        $id = (int) $this->request->getId();
        self::assertSame(1, $this->resource->claimEmailAttempt($id));
        $before = $this->row();
        self::assertSame(1, $this->resource->updateRequestStatuses([$id, (string) $id], 'closed'));
        $after = $this->row();
        self::assertSame('closed', $after['request_status']);
        foreach (['email_status', 'email_attempts', 'email_lease_started_at', 'customer_name', 'customer_email',
            'product_name', 'quantity', 'message', 'idempotency_key'] as $column) {
            self::assertSame($before[$column], $after[$column]);
        }
    }
}
