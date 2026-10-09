<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Ui\DataProvider\FulltextFilter;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FulltextFilterTest extends TestCase
{
    public function testFixedFieldsAreOrMatchedWithLiteralWildcardsAndQuotes(): void
    {
        $collection = $this->getMockBuilder(AbstractDb::class)->disableOriginalConstructor()->onlyMethods(['addFieldToFilter'])->getMock();
        $collection->expects(self::once())->method('addFieldToFilter')->with(
            ['main_table.customer_name', 'main_table.customer_email', 'main_table.customer_telephone',
                'main_table.product_name', 'main_table.product_sku', 'main_table.message'],
            array_fill(0, 6, ['like' => "%a\\%\\_\\\\' OR 1=1 --%"])
        )->willReturnSelf();
        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn("  a%_\\' OR 1=1 --  ");
        (new FulltextFilter())->apply($collection, $filter);
    }

    public function testBlankQueryDoesNotChangeCollection(): void
    {
        $collection = $this->getMockBuilder(AbstractDb::class)->disableOriginalConstructor()->onlyMethods(['addFieldToFilter'])->getMock();
        $collection->expects(self::never())->method('addFieldToFilter');
        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn(" \n\t ");
        (new FulltextFilter())->apply($collection, $filter);
    }

    public static function invalidQueries(): array
    {
        return [[null], [[]], [true], [1], [str_repeat('x', 513)]];
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueryRejectsBeforeAddingCondition(mixed $value): void
    {
        $collection = $this->getMockBuilder(AbstractDb::class)->disableOriginalConstructor()->onlyMethods(['addFieldToFilter'])->getMock();
        $collection->expects(self::never())->method('addFieldToFilter');
        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn($value);
        $this->expectException(LocalizedException::class);
        (new FulltextFilter())->apply($collection, $filter);
    }

    public function testNonDatabaseCollectionRejectsInsteadOfChangingGlobalCollectionBehavior(): void
    {
        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn('search');
        $this->expectException(LocalizedException::class);
        (new FulltextFilter())->apply($this->createMock(Collection::class), $filter);
    }
}
