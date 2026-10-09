<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PurchasingPolicyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('contexts')]
    public function testExistingQuoteStoreAndCurrentStoreAreBothChecked(string $area, int $currentStore, ?int $quoteStore, array $blocked, bool $shouldBlock): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPurchasingDisabled')->willReturnCallback(static fn ($id) => in_array($id, $blocked, true));
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($currentStore);
        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);
        $policy = new PurchasingPolicy($config, $manager, $state);
        if ($shouldBlock) {
            $this->expectException(LocalizedException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }
        $policy->assertAllowed($quoteStore);
    }

    public static function contexts(): array
    {
        return [['frontend', 1, null, [1], true], ['webapi_rest', 2, 1, [1], true],
            ['graphql', 2, 1, [1], true], ['graphql', 1, 2, [1], true],
            ['frontend', 2, 2, [1], false], ['adminhtml', 1, 1, [1], false],
            ['crontab', 1, 1, [1], false]];
    }
}
