<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Catalog\QuoteStoreResolver;
use Coresh\QuoteRequest\Plugin\Catalog\QuoteRepositoryPurchasingGuard;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

class QuoteRepositoryGuardTest extends TestCase
{
    public function testChangedInMemoryStoreDoesNotBypassPersistedStoreRule(): void
    {
        $policy = $this->createMock(PurchasingPolicy::class);
        $seen = [];
        $policy->method('assertAllowed')->willReturnCallback(static function ($storeId) use (&$seen) {
            $seen[] = $storeId;
            if ($storeId === 1) {
                throw new LocalizedException(__('Blocked'));
            }
        });
        $origin = $this->createMock(QuoteStoreResolver::class);
        $origin->expects(self::once())->method('getPersistedStoreId')->with(7)->willReturn(1);
        $quote = $this->createMock(CartInterface::class);
        $quote->method('getStoreId')->willReturn(2);
        $quote->method('getId')->willReturn(7);
        $repository = $this->createMock(CartRepositoryInterface::class);
        try {
            (new QuoteRepositoryPurchasingGuard($policy, $origin))->beforeSave($repository, $quote);
            self::fail('Changed store must still reject persisted blocked store.');
        } catch (LocalizedException $exception) {
            self::assertSame([2, 1], $seen);
        }
    }
}
