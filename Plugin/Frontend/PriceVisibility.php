<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Frontend;

use Coresh\QuoteRequest\ViewModel\ProductQuoteRequest;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Pricing\Render\PriceBox;

/** HTML presentation only; APIs and other structured data are not filtered. */
class PriceVisibility
{
    public function __construct(
        private readonly ProductQuoteRequest $viewModel
    ) {
    }

    public function afterToHtml(PriceBox $subject, string $result): string
    {
        $product = $subject->getSaleableItem();
        return $product instanceof ProductInterface
            && $this->viewModel->shouldHideProductPrice($product) ? '' : $result;
    }
}
