<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Block\Adminhtml;

use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\Block\Template;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

class RequestDetails extends Template
{
    private ?QuoteRequest $quoteRequest = null;

    public function __construct(
        Template\Context $context,
        private readonly QuoteRequestFactory $quoteRequestFactory,
        private readonly QuoteRequestResource $quoteRequestResource,
        private readonly AuthorizationInterface $authorization,
        private readonly FormKey $formKey,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): QuoteRequest
    {
        if ($this->quoteRequest === null) {
            $this->quoteRequest = $this->quoteRequestFactory->create();
            $id = $this->getRequest()->getParam('request_id');
            if (is_scalar($id) && ctype_digit((string) $id)) {
                $this->quoteRequestResource->load($this->quoteRequest, (int) $id);
            }
        }
        return $this->quoteRequest;
    }

    public function canManage(): bool
    {
        return $this->authorization->isAllowed('Coresh_QuoteRequest::manage');
    }

    public function canSendEmail(): bool
    {
        return $this->authorization->isAllowed('Coresh_QuoteRequest::send_email');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getStoreLabel(): string
    {
        $id = $this->getQuoteRequest()->getData('store_id');
        if ($id === null) {
            return (string) __('Deleted store');
        }
        try {
            $store = $this->storeManager->getStore((int) $id);
            return $store->getName() . ' (' . $store->getCode() . ')';
        } catch (\Throwable $exception) {
            return (string) __('Deleted store #%1', (int) $id);
        }
    }

    public function getSnapshotRows(): array
    {
        $request = $this->getQuoteRequest();
        $fields = [
            'product_name' => __('Product Name'), 'product_id' => __('Product ID'),
            'quantity' => __('Quantity'), 'quantity_origin' => __('Quantity Origin'),
            'product_sku' => __('SKU'), 'product_url' => __('Product URL'),
            'product_type' => __('Product Type'), 'selected_product_id' => __('Selected Variant ID'),
            'selected_product_sku' => __('Selected Variant SKU'), 'selected_options' => __('Selected Options'),
            'customer_id' => __('Customer ID'), 'customer_name' => __('Customer Name'),
            'customer_email' => __('Customer Email'), 'customer_telephone' => __('Customer Telephone'),
            'message' => __('Message'), 'request_status' => __('Request Status'),
            'email_status' => __('Email Status'), 'email_attempts' => __('Email Attempts'),
            'last_email_sent_at' => __('Last Email Sent (UTC)'), 'last_error_code' => __('Last Error Code'),
            'created_at' => __('Created At (UTC)'), 'updated_at' => __('Updated At (UTC)')
        ];
        $rows = [];
        foreach ($fields as $field => $label) {
            $value = $request->getData($field);
            $rows[] = ['label' => $label, 'value' => $value === null ? (string) __('Unknown / not provided') : (string) $value];
        }
        return $rows;
    }
}
