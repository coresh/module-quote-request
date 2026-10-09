<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testStoreContextIsDelegatedToMagentoInheritance(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::once())->method('getValue')->with('coresh_quote_request/general/button_label', 'store', 2)
            ->willReturn('  Request quotation  ');
        self::assertSame('Request quotation', (new Config($scope))->getButtonLabel(2));
    }

    public function testRecipientFallsBackWithinSameStore(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(static function ($path, $scopeType, $storeId) {
            self::assertSame('store', $scopeType);
            self::assertSame(7, $storeId);
            return $path === 'contacts/email/recipient_email' ? ' contact@example.test ' : '';
        });
        self::assertSame('contact@example.test', (new Config($scope))->getRecipient(7));
    }

    public function testConfiguredRecipientTakesPrecedence(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->expects(self::once())->method('getValue')->with('coresh_quote_request/email/recipient', 'store', 1)
            ->willReturn(' sales@example.test ');
        self::assertSame('sales@example.test', (new Config($scope))->getRecipient(1));
    }

    public function testDefaultLabelForEmptyValueAndNonScalarConfigIsSafe(): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturn([]);
        self::assertSame('', (new Config($scope))->getValue('email/recipient', 1));
        self::assertSame('Contact Us for a Quote', (new Config($scope))->getButtonLabel(1));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modeFlags')]
    public function testCatalogSettingsRemainIndependent(bool $enabled, bool $cart, bool $buy, bool $prices): void
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturnCallback(static fn ($path) => [
            'coresh_quote_request/general/enabled' => $enabled,
            'coresh_quote_request/catalog/hide_cart' => $cart,
            'coresh_quote_request/catalog/disable_purchasing' => $buy,
            'coresh_quote_request/catalog/hide_prices' => $prices,
        ][$path] ?? false);
        $config = new Config($scope);
        self::assertSame($enabled && $cart, $config->isCartHidden(1));
        self::assertSame($enabled && $buy, $config->isPurchasingDisabled(1));
        self::assertSame($enabled && $prices, $config->shouldHidePrices(1));
    }

    public static function modeFlags(): array
    {
        return [[true, false, false, false], [true, true, false, false], [true, false, true, false],
            [true, true, true, false], [false, true, true, true], [true, false, false, true]];
    }
}
