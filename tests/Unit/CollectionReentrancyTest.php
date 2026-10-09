<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Plugin\Catalog\ProductCollectionContext;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CollectionReentrancyTest extends TestCase
{
    public function testGetItemsReenteringAfterLoadTerminatesAndPerformsOneBatch(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->expects(self::once())->method('where')->with('product_id IN (?)', [42, 43])->willReturnSelf();
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->expects(self::once())->method('fetchAll')->willReturn([
            ['product_id' => 42, 'category_id' => 10], ['product_id' => 42, 'category_id' => 11]
        ]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('catalog_category_product');
        $first = $this->createMock(Product::class);
        $first->method('getId')->willReturn(42);
        $first->method('hasData')->willReturn(false);
        $first->expects(self::once())->method('setData')->with('category_ids', [10, 11]);
        $second = $this->createMock(Product::class);
        $second->method('getId')->willReturn(43);
        $second->method('hasData')->willReturn(false);
        $second->expects(self::once())->method('setData')->with('category_ids', []);
        $plugin = new ProductCollectionContext($config, $stores, $resource);
        $collection = $this->createMock(Collection::class);
        $calls = 0;
        $collection->method('getItems')->willReturnCallback(static function () use (&$calls, $plugin, $collection, $first, $second) {
            if (++$calls > 2) {
                throw new \RuntimeException('Recursive afterLoad did not terminate.');
            }
            // Real Magento Collection::getItems calls intercepted load again.
            $plugin->afterLoad($collection, $collection);
            return [$first, $second];
        });
        self::assertSame($collection, $plugin->afterLoad($collection, $collection));
        self::assertSame(1, $calls);
    }
}
