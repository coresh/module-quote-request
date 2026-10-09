<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Eligibility\CategoryRuleResolver;
use Coresh\QuoteRequest\Model\Eligibility\DateRangePolicy;
use Coresh\QuoteRequest\Model\Eligibility\QuoteRequestEligibility;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\TestCase;

class EligibilityTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('truthTable')]
    public function testEligibilityRequiresEveryGateAndOneSelectionRule(
        bool $enabled, bool $dateAllowed, bool $all, bool $productFlag, bool $categoryMatch, string $groups, int $group, bool $expected
    ): void {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('isFlag')->willReturnCallback(static fn ($key) =>
            ($key === 'general/apply_to_all_products' && $all)
            || ($key === 'general/restrict_customer_groups' && trim($groups) !== ''));
        $config->method('getValue')->willReturnCallback(static fn ($key) => $key === 'general/customer_groups' ? $groups : '');
        $dates = $this->createMock(DateRangePolicy::class);
        $dates->method('isAllowed')->willReturn($dateAllowed);
        $categories = $this->createMock(CategoryRuleResolver::class);
        $categories->method('matches')->willReturn($categoryMatch);
        $context = $this->createMock(Context::class);
        $product = $this->createMock(ProductInterface::class);
        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getValue')->willReturn(1);
        $product->method('getCustomAttribute')->willReturn($productFlag ? $attribute : null);
        $product->method('getExtensionAttributes')->willReturn(null);
        self::assertSame($expected, (new QuoteRequestEligibility($config, $dates, $categories, $context))->canRequestQuote($product, 1, $group));
    }

    public static function truthTable(): array
    {
        return [
            [false, true, true, true, true, '', 0, false],
            [true, false, true, true, true, '', 0, false],
            [true, true, false, false, false, '', 0, false],
            [true, true, true, false, false, '', 0, true],
            [true, true, false, true, false, '', 0, true],
            [true, true, false, false, true, '', 0, true],
            [true, true, true, false, false, '1,2', 0, false],
            [true, true, true, false, false, '0,1', 0, true],
            [true, true, true, false, false, '0,1', 1, true],
            [true, true, true, false, false, '0,1', 2, false],
            [true, true, false, true, false, '2', 2, true],
            [true, true, false, true, false, '2', 0, false],
            [true, true, true, false, false, 'invalid', 0, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('explicitGroupRestrictions')]
    public function testRestrictionToggleAndEmptySelection(bool $restricted, string $groups, int $groupId, bool $expected): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->with('general/restrict_customer_groups', 2)->willReturn($restricted);
        $config->method('getValue')->willReturn($groups);
        $dates = $this->createMock(DateRangePolicy::class);
        $categories = $this->createMock(CategoryRuleResolver::class);
        $context = $this->createMock(Context::class);
        self::assertSame($expected, (new QuoteRequestEligibility($config, $dates, $categories, $context))
            ->isCustomerGroupAllowed(2, $groupId));
    }

    public static function explicitGroupRestrictions(): array
    {
        return [[false, '', 0, true], [false, '1,2', 0, true], [false, 'invalid', 3, true],
            [true, '', 0, false], [true, '   ', 1, false], [true, '0', 0, true],
            [true, '1,2', 0, false], [true, '1,2', 2, true], [true, 'bad, 0 ,1', 0, true],
            [true, 'invalid', 0, false]];
    }

    public function testMissingExplicitGroupUsesCurrentPrivateContext(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->willReturn(true);
        $config->method('getValue')->willReturn('2');
        $context = $this->createMock(Context::class);
        $context->expects(self::once())->method('getValue')->with('customer_group')->willReturn(2);
        self::assertTrue((new QuoteRequestEligibility($config, $this->createMock(DateRangePolicy::class),
            $this->createMock(CategoryRuleResolver::class), $context))->isCustomerGroupAllowed(1));
    }
}
