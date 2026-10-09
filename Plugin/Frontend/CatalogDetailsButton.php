<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Frontend;

use Coresh\QuoteRequest\ViewModel\ProductQuoteRequest;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\Escaper;

class CatalogDetailsButton
{
    public function __construct(
        private readonly ProductQuoteRequest $viewModel,
        private readonly Escaper $escaper
    ) {
    }

    public function afterGetProductDetailsHtml(ListProduct $subject, string $result, $product): string
    {
        if (!$product instanceof ProductInterface || !$this->viewModel->isProductEligible($product)) {
            return $result;
        }
        $label = $this->viewModel->getButtonLabel($product);
        $context = $this->escaper->escapeHtmlAttr($this->viewModel->getContextJson($product));
        if ($label === '') {
            return $result . '<span hidden data-coresh-display="' . $context . '"></span>';
        }
        if ($this->viewModel->isStaticLabelDisplay()) {
            return $result . '<div class="coresh-quote-request-list"><span class="coresh-quote-label"'
                . ' data-coresh-display="' . $context . '">'
                . $this->escaper->escapeHtml($label) . '</span></div>';
        }
        if (!$this->viewModel->canRequestQuote($product)) {
            return $result;
        }
        $class = $this->viewModel->isLinkDisplay() ? 'action coresh-quote-link' : 'action primary';
        return $result . '<div class="coresh-quote-request-list">'
            . '<button type="button" class="' . $class . '" data-coresh-quote="'
            . $context . '">'
            . $this->escaper->escapeHtml($label) . '</button></div>';
    }
}
