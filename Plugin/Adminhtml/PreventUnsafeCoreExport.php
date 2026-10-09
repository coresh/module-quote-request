<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Adminhtml;

use Coresh\QuoteRequest\Ui\Service\GridSelectionValidator;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;

/** Disallow only this listing in stock converters, which do not sanitize CSV formulas. */
class PreventUnsafeCoreExport
{
    public function __construct(private readonly RequestInterface $request)
    {
    }

    public function beforeGetCsvFile($subject): void
    {
        $this->assertSafeRoute();
    }

    public function beforeGetXmlFile($subject): void
    {
        $this->assertSafeRoute();
    }

    private function assertSafeRoute(): void
    {
        if ($this->request->getParam('namespace') === GridSelectionValidator::NAMESPACE) {
            throw new LocalizedException(__('Export quote requests using the CSV option in the Quote Requests listing.'));
        }
    }
}
