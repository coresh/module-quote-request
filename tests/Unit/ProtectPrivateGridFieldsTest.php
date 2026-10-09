<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Plugin\Adminhtml\ProtectPrivateGridFields;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use PHPUnit\Framework\TestCase;

class ProtectPrivateGridFieldsTest extends TestCase
{
    public function testPrivateDigestsAreRemovedFromOurListingAjaxRows(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getName')->willReturn('coresh_quote_request_listing_data_source');
        $result = (new ProtectPrivateGridFields())->afterGetData($provider, [
            'totalRecords' => 2,
            'items' => [
                ['request_id' => 1, 'customer_name' => 'Name', 'idempotency_key' => 'private-token-digest',
                    'payload_hash' => 'private-payload-digest', 'email_status' => 'sent'],
                ['request_id' => 2, 'idempotency_key' => null, 'payload_hash' => null]
            ]
        ]);
        self::assertSame(2, $result['totalRecords']);
        self::assertSame([
            ['request_id' => 1, 'customer_name' => 'Name', 'email_status' => 'sent'], ['request_id' => 2]
        ], $result['items']);
    }

    public function testUnrelatedDataSourceIsNotModified(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getName')->willReturn('sales_order_grid_data_source');
        $data = ['items' => [['request_id' => 1, 'idempotency_key' => 'unrelated-field',
            'payload_hash' => 'unrelated-field']], 'totalRecords' => 1];
        self::assertSame($data, (new ProtectPrivateGridFields())->afterGetData($provider, $data));
    }

    public function testEmptyAndUnexpectedProviderShapesRemainIntact(): void
    {
        $provider = $this->createMock(DataProvider::class);
        $provider->method('getName')->willReturn('coresh_quote_request_listing_data_source');
        foreach ([[], ['items' => null], ['items' => 'not-an-array'],
            ['items' => [null, 'not-an-array', ['request_id' => 1]]]] as $data
        ) {
            self::assertSame($data, (new ProtectPrivateGridFields())->afterGetData($provider, $data));
        }
    }
}
