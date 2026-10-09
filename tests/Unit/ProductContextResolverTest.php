<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Eligibility\QuoteRequestEligibility;
use Coresh\QuoteRequest\Model\Product\ProductContextResolver;
use Coresh\QuoteRequest\Model\Catalog\ProductDisplayPolicy;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ProductContextResolverTest extends TestCase
{
    private function product(string $type = 'simple', array $websites = [1], int $status = 1, int $visibility = 4): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(42);
        $product->method('getWebsiteIds')->willReturn($websites);
        $product->method('getStatus')->willReturn($status);
        $product->method('getVisibility')->willReturn($visibility);
        $product->method('getTypeId')->willReturn($type);
        $product->method('getName')->willReturn('Server product name');
        $product->method('getSku')->willReturn('SERVER42');
        $product->method('getProductUrl')->willReturn('https://shop.example.test/product.html');
        $product->method('getData')->willReturnCallback(static fn ($field) => ['name' => 'Server product name', 'sku' => 'SERVER42'][$field] ?? null);
        return $product;
    }

    private function resolver(Product $product, bool $eligible = true, bool $decimal = false, ?Configurable $configurable = null, bool $inquiryAllowed = true): ProductContextResolver
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('getById')->with(42, false, 2, true)->willReturn($product);
        $store = $this->createMock(Store::class);
        $store->method('isActive')->willReturn(true);
        $store->method('getWebsiteId')->willReturn(1);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(2)->willReturn($store);
        $eligibility = $this->createMock(QuoteRequestEligibility::class);
        $eligibility->method('canRequestQuote')->willReturn($eligible);
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getIsQtyDecimal')->willReturn($decimal);
        $stock = $this->createMock(StockRegistryInterface::class);
        $stock->method('getStockItem')->willReturn($stockItem);
        $display = $this->createMock(ProductDisplayPolicy::class);
        $display->method('isInquiryAllowed')->with($product, 2)->willReturn($inquiryAllowed);
        return new ProductContextResolver($repository, $stores, $eligibility,
            $configurable ?? $this->createMock(Configurable::class), $stock, $display);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('supportedTypes')]
    public function testSupportedParentRequestsUseServerSnapshot(string $type, bool $parentOnly): void
    {
        $context = $this->resolver($this->product($type))->resolve(42, 2, 0);
        self::assertSame(42, $context['product_id']);
        self::assertSame('Server product name', $context['product_name']);
        self::assertSame('SERVER42', $context['product_sku']);
        self::assertNull($context['selected_product_id']);
        if ($parentOnly) {
            self::assertSame('parent_only', json_decode($context['selected_options'], true)['inquiry_scope']);
        } else {
            self::assertNull($context['selected_options']);
        }
    }

    public static function supportedTypes(): array
    {
        return [['simple', false], ['virtual', false], ['downloadable', false],
            ['configurable', true], ['bundle', true], ['grouped', true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAvailability')]
    public function testUnassignedDisabledHiddenOrIneligibleProductFailsClosed(array $websites, int $status, int $visibility, bool $eligible): void
    {
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product('simple', $websites, $status, $visibility), $eligible)->resolve(42, 2, 0);
    }

    public static function invalidAvailability(): array
    {
        return [[[3], 1, 4, true], [[1], 2, 4, true], [[1], 1, 1, true], [[1], 1, 4, false]];
    }

    public function testNonConfigurableOptionsAreRejected(): void
    {
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product())->resolve(42, 2, 0, [12 => 3]);
    }

    public function testConfigurableOptionsMustMatchRealParentAttributes(): void
    {
        $type = $this->createMock(Configurable::class);
        $type->method('getConfigurableAttributes')->willReturn([]);
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product('configurable'), true, false, $type)->resolve(42, 2, 0, [12 => 3]);
    }

    public function testWholeUnitProductRejectsFractionWithoutReservingStock(): void
    {
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product(), true, false)->assertQuantityAllowed(['product_type' => 'simple', 'product_id' => 42], '1.5000', 2);
    }

    public function testDecimalProductAllowsFractionWithoutInventoryReservation(): void
    {
        $this->resolver($this->product(), true, true)->assertQuantityAllowed(['product_type' => 'simple', 'product_id' => 42], '1.5000', 2);
        $this->addToAssertionCount(1);
    }

    public function testUnselectedBundleRequiresWholeParentUnits(): void
    {
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product('bundle'))->assertQuantityAllowed(['product_type' => 'bundle', 'product_id' => 42], '1.5000', 2);
    }

    public function testHiddenInquiryRejectsDirectPostEvenWhenProductIsEligible(): void
    {
        $this->expectException(LocalizedException::class);
        $this->resolver($this->product(), true, false, null, false)->resolve(42, 2, 0);
    }
}
