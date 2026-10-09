<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\ViewModel;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Catalog\ProductDisplayPolicy;
use Coresh\QuoteRequest\Model\Eligibility\QuoteRequestEligibility;
use Coresh\QuoteRequest\Model\Validation\QuoteRequestValidator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/** Public product context only. Customer PII is obtained from private content. */
class ProductQuoteRequest implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly QuoteRequestEligibility $eligibility,
        private readonly StoreManagerInterface $storeManager,
        private readonly Context $httpContext,
        private readonly UrlInterface $url,
        private readonly Json $json,
        private readonly QuoteRequestValidator $validator,
        private readonly ProductDisplayPolicy $displayPolicy
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled($this->getStoreId());
    }

    public function canRequestQuote(ProductInterface $product): bool
    {
        return $this->isProductEligible($product)
            && $this->displayPolicy->isInquiryAllowed($product, $this->getStoreId());
    }

    public function isProductEligible(ProductInterface $product): bool
    {
        return $this->eligibility->canRequestQuote(
            $product,
            $this->getStoreId(),
            (int) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP)
        );
    }

    public function getButtonLabel(?ProductInterface $product = null): string
    {
        return $product === null ? $this->config->getButtonLabel($this->getStoreId())
            : $this->displayPolicy->getButtonLabel($product, $this->getStoreId());
    }

    public function getSubmitUrl(): string
    {
        return $this->url->getUrl('quoterequest/request/submit', ['_secure' => true]);
    }

    public function shouldShowInquiryTab(): bool
    {
        return $this->isEnabled() && $this->config->isFlag('general/show_inquiry_tab', $this->getStoreId());
    }

    public function shouldSuppressPurchase(ProductInterface $product): bool
    {
        return $this->isEnabled() && (
            $this->config->isPurchasingDisabled($this->getStoreId())
            || ($this->isProductEligible($product)
                && !$this->displayPolicy->isAddToCartAllowed($product, $this->getStoreId()))
        );
    }

    public function isLinkDisplay(): bool
    {
        return $this->config->getValue('general/request_display', $this->getStoreId()) === 'link';
    }

    public function isStaticLabelDisplay(): bool
    {
        return $this->config->getValue('general/request_display', $this->getStoreId()) === 'label';
    }

    public function shouldHideProductPrice(ProductInterface $product): bool
    {
        return $this->isProductEligible($product)
            && $this->displayPolicy->shouldHidePrice($product, $this->getStoreId());
    }

    public function getContextJson(ProductInterface $product): string
    {
        return $this->json->serialize($this->getProductContext($product));
    }

    private function getProductContext(ProductInterface $product): array
    {
        return [
            'productId' => (int) $product->getId(),
            'productName' => (string) $product->getName(),
            'suppressPurchase' => $this->shouldSuppressPurchase($product)
        ];
    }

    public function getInitializationJson(?ProductInterface $product = null, bool $inline = false): string
    {
        return $this->json->serialize([
            'Coresh_QuoteRequest/js/quote-request' => [
                'submitUrl' => $this->getSubmitUrl(),
                'disablePurchasing' => $this->config->isPurchasingDisabled($this->getStoreId()),
                'inline' => $inline,
                'productContext' => $product === null ? null : $this->getProductContext($product)
            ]
        ]);
    }

    public function getStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    public function getMaximumQuantity(): string
    {
        return $this->validator->getMaximumQuantity($this->getStoreId());
    }

    public function getMaximumMessageLength(): int
    {
        return $this->validator->getMaximumMessageLength($this->getStoreId());
    }
}
