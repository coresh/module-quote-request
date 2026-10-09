<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Plugin\Adminhtml\PreventUnsafeCoreExport;
use Coresh\QuoteRequest\Ui\Service\GridSelectionValidator;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PreventUnsafeCoreExportTest extends TestCase
{
    public static function converters(): array
    {
        return [['beforeGetCsvFile'], ['beforeGetXmlFile']];
    }

    #[DataProvider('converters')]
    public function testOurListingCannotBypassSanitizerThroughStockExport(string $method): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('namespace')->willReturn(GridSelectionValidator::NAMESPACE);
        $this->expectException(LocalizedException::class);
        (new PreventUnsafeCoreExport($request))->$method(new \stdClass());
    }

    #[DataProvider('converters')]
    public function testUnrelatedListingRemainsAvailable(string $method): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with('namespace')->willReturn('sales_order_grid');
        (new PreventUnsafeCoreExport($request))->$method(new \stdClass());
        $this->addToAssertionCount(1);
    }
}
