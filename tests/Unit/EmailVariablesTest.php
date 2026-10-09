<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\TemplateVariablesBuilder;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class EmailVariablesTest extends TestCase
{
    private function variables(array $flags = [], array $replace = [], bool $honor = true): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->willReturnCallback(static function ($path, $storeId) use ($flags) {
            self::assertSame(2, $storeId);
            return $flags[$path] ?? true;
        });
        $store = $this->createMock(Store::class);
        $store->method('getName')->willReturn('Chinese store');
        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->expects(self::once())->method('getStore')->with(2)->willReturn($store);
        $data = array_replace(['store_id' => 2, 'product_name' => 'Trusted product', 'product_id' => 42,
            'quantity' => '3.0000', 'product_sku' => 'SKU42', 'product_url' => 'https://shop.example.test/product.html',
            'selected_product_id' => 43, 'selected_options' => '', 'customer_name' => 'Customer',
            'customer_email' => 'customer@example.test', 'message' => '<script>alert(1)</script> {{var secret}}'], $replace);
        $request = $this->createMock(QuoteRequest::class);
        $request->method('getData')->willReturnCallback(static fn ($key) => $data[$key] ?? null);
        $request->method('getId')->willReturn(7);
        return (new TemplateVariablesBuilder($config, $manager))->build($request, $honor);
    }

    public function testAllRequestedFieldsArePresent(): void
    {
        $vars = $this->variables();
        self::assertSame('Trusted product', $vars['product_name']);
        self::assertSame('3.0000', $vars['quantity']);
        self::assertSame('42', $vars['product_id']);
        self::assertTrue($vars['show_product_name']);
        self::assertTrue($vars['show_quantity']);
        self::assertTrue($vars['show_product_id']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('inclusionFlags')]
    public function testDisabledEmailFieldIsExcluded(string $field): void
    {
        $vars = $this->variables(['email/include_' . $field => false]);
        self::assertSame('', $vars[$field]);
        self::assertFalse($vars['show_' . $field]);
        if ($field === 'product_id') {
            self::assertSame('', $vars['selected_product_id']);
        }
    }

    public static function inclusionFlags(): array
    {
        return [['product_name'], ['quantity'], ['product_id']];
    }

    public function testLegacyUnknownQuantityIsNotInvented(): void
    {
        self::assertSame('Not recorded', $this->variables([], ['quantity' => null])['quantity']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeUrls')]
    public function testInvalidProductUrlIsExcludedFromEmail(string $url): void
    {
        $vars = $this->variables([], ['product_url' => $url]);
        self::assertSame('', $vars['product_url']);
        self::assertFalse($vars['has_product_url']);
    }

    public static function unsafeUrls(): array
    {
        return [['javascript:alert(1)'], ['data:text/html,payload'], ['//attacker.test'],
            ['https://user:pass@example.test/'], ['https:///missing-host']];
    }

    public function testParentOnlyBundleIsExplicitInEmail(): void
    {
        $vars = $this->variables([], ['selected_options' => '{"inquiry_scope":"parent_only"}']);
        self::assertStringContainsString('configuration has not been selected', $vars['selected_options']);
    }

    public function testTextRemainsScalarForStrictEscapedTemplateRendering(): void
    {
        $vars = $this->variables();
        self::assertSame('<script>alert(1)</script> {{var secret}}', $vars['message']);
        foreach ($vars as $value) {
            self::assertTrue(is_scalar($value));
        }
    }
}
