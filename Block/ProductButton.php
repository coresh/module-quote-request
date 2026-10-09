<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Block;

use Coresh\QuoteRequest\ViewModel\ProductQuoteRequest;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;

/** Registry is Magento's current PDP context; no repository loads in rendering. */
class ProductButton extends Template implements IdentityInterface
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly ProductQuoteRequest $quoteRequestViewModel,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    public function getQuoteRequestViewModel(): ProductQuoteRequest
    {
        return $this->quoteRequestViewModel;
    }

    public function getIdentities(): array
    {
        $product = $this->getProduct();
        $tags = ['coresh_quote_request'];
        if ($product !== null) {
            $tags[] = 'cat_p_' . (int) $product->getId();
            if ($product instanceof IdentityInterface) {
                $tags = array_merge($tags, $product->getIdentities());
            }
        }
        return array_values(array_unique($tags));
    }

    protected function _toHtml()
    {
        $product = $this->getProduct();
        if ($product === null || !$this->quoteRequestViewModel->isProductEligible($product)) {
            return '';
        }
        if ($this->getData('is_inquiry_tab') && (
            !$this->quoteRequestViewModel->shouldShowInquiryTab()
            || !$this->quoteRequestViewModel->canRequestQuote($product)
        )) {
            return '';
        }
        return parent::_toHtml();
    }
}
