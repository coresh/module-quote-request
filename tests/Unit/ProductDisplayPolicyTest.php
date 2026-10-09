<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Catalog\ProductDisplayPolicy;
use Coresh\QuoteRequest\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\TestCase;

class ProductDisplayPolicyTest extends TestCase
{
    private function product(array $attributes): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getCustomAttribute')->willReturnCallback(function ($code) use ($attributes) {
            if (!array_key_exists($code, $attributes)) {
                return null;
            }
            $attribute = $this->createMock(AttributeInterface::class);
            $attribute->method('getValue')->willReturn($attributes[$code]);
            return $attribute;
        });
        return $product;
    }

    private function policy(bool $enabled = true, bool $buyDisabled = false, bool $allowCart = true, bool $hidePrice = false, string $display = 'button'): ProductDisplayPolicy
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isPurchasingDisabled')->willReturn($buyDisabled);
        $config->method('isFlag')->with('catalog/allow_add_to_cart', 2)->willReturn($allowCart);
        $config->method('shouldHidePrices')->willReturn($hidePrice);
        $config->method('getButtonLabel')->with(2)->willReturn('Global quote request');
        $config->method('getValue')->with('general/request_display', 2)->willReturn($display);
        return new ProductDisplayPolicy($config);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('labels')]
    public function testInheritedCustomAndBlankLabels(array $attributes, string $expected, bool $inquiry): void
    {
        $product = $this->product($attributes);
        $policy = $this->policy();
        self::assertSame($expected, $policy->getButtonLabel($product, 2));
        self::assertSame($inquiry, $policy->isInquiryAllowed($product, 2));
    }

    public static function labels(): array
    {
        $flag = 'coresh_quote_request_enabled';
        $mode = ProductDisplayPolicy::LABEL_MODE;
        $label = ProductDisplayPolicy::BUTTON_LABEL;
        return [
            [[], 'Global quote request', true],
            [[$flag => 1, $label => 'Legacy label without explicit custom mode'], 'Global quote request', true],
            [[$flag => 1, $mode => 'inherit', $label => 'Unused custom'], 'Global quote request', true],
            [[$flag => 1, $mode => 'custom', $label => '  Product quotation  '], 'Product quotation', true],
            [[$flag => 1, $mode => 'custom', $label => ''], '', false],
            [[$flag => '1', $mode => 'custom', $label => '  '], '', false],
            [[$flag => 1, $mode => 'custom'], '', false],
            [[$flag => 0, $mode => 'custom', $label => ''], 'Global quote request', true],
            [[$flag => 'invalid', $mode => 'custom', $label => 'Override'], 'Global quote request', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cartSettings')]
    public function testCartOverrideInheritanceAndServerRestriction(
        bool $module, bool $buyDisabled, bool $globalCart, mixed $flag, mixed $override, bool $expected
    ): void {
        self::assertSame($expected, $this->policy($module, $buyDisabled, $globalCart)->isAddToCartAllowed(
            $this->product(['coresh_quote_request_enabled' => $flag, ProductDisplayPolicy::ALLOW_CART => $override]), 2));
    }

    public static function cartSettings(): array
    {
        return [[true, false, true, 1, -1, true], [true, false, false, 1, -1, false],
            [true, false, false, 1, 1, true], [true, false, true, 1, 0, false],
            [true, true, true, 1, 1, false], [false, true, false, 1, 0, true],
            [true, false, false, 0, 1, false], [true, false, true, 0, 0, true],
            [true, false, true, 1, null, true], [true, false, false, 1, 'invalid', false],
            [true, false, false, '1', '1', true], [true, false, true, '1', '0', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('priceSettings')]
    public function testPriceOverrideIsIndependentFromCart(
        bool $module, bool $globalHidePrice, mixed $flag, mixed $override, bool $expectedHide
    ): void {
        self::assertSame($expectedHide, $this->policy($module, true, false, $globalHidePrice)->shouldHidePrice(
            $this->product(['coresh_quote_request_enabled' => $flag, ProductDisplayPolicy::SHOW_PRICE => $override]), 2));
    }

    public static function priceSettings(): array
    {
        return [[true, false, 1, -1, false], [true, true, 1, -1, true],
            [true, true, 1, 1, false], [true, false, 1, 0, true],
            [false, true, 1, 0, false], [true, true, 0, 1, true],
            [true, false, 0, 0, false], [true, true, 1, null, true],
            [true, false, 1, 'invalid', false], [true, true, '1', '1', false],
            [true, false, '1', '0', true]];
    }

    public function testStaticLabelRemainsVisibleTextButCannotOpenOrPostAnEnquiry(): void
    {
        $product = $this->product(['coresh_quote_request_enabled' => 1]);
        $policy = $this->policy(true, false, true, false, 'label');
        self::assertSame('Global quote request', $policy->getButtonLabel($product, 2));
        self::assertFalse($policy->isInquiryAllowed($product, 2));
    }
}
