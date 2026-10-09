<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Email;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Magento\Store\Model\StoreManagerInterface;

/** Scalar variables are compatible with Magento's strict email template mode. */
class TemplateVariablesBuilder
{
    public function __construct(private readonly Config $config, private readonly StoreManagerInterface $stores)
    {
    }

    public function build(QuoteRequest $request, bool $honorProductFields = true): array
    {
        $storeId = (int) $request->getData('store_id');
        $store = $this->stores->getStore($storeId);
        $name = !$honorProductFields || $this->config->isFlag('email/include_product_name', $storeId);
        $quantity = !$honorProductFields || $this->config->isFlag('email/include_quantity', $storeId);
        $id = !$honorProductFields || $this->config->isFlag('email/include_product_id', $storeId);
        $url = (string) $request->getData('product_url');
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || !in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])
        ) {
            $url = '';
        }
        $savedQuantity = $request->getData('quantity');
        return [
            'request_id' => (int) $request->getId(),
            'created_at' => (string) $request->getData('created_at'),
            'store_name' => (string) $store->getName(),
            'customer_name' => (string) $request->getData('customer_name'),
            'customer_email' => (string) $request->getData('customer_email'),
            'customer_telephone' => (string) $request->getData('customer_telephone'),
            'message' => (string) $request->getData('message'),
            'show_product_name' => $name,
            'product_name' => $name ? (string) $request->getData('product_name') : '',
            'show_quantity' => $quantity,
            'quantity' => $quantity ? ($savedQuantity === null ? (string) __('Not recorded') : (string) $savedQuantity) : '',
            'show_product_id' => $id,
            'product_id' => $id ? (string) $request->getData('product_id') : '',
            'product_sku' => (string) $request->getData('product_sku'),
            'product_type' => (string) $request->getData('product_type'),
            'product_url' => $url,
            'has_product_url' => $url !== '',
            'selected_product_id' => $id ? (string) $request->getData('selected_product_id') : '',
            'selected_product_sku' => (string) $request->getData('selected_product_sku'),
            'has_selected_product' => (bool) $request->getData('selected_product_id'),
            'show_selected_product_id' => $id && (bool) $request->getData('selected_product_id'),
            'selected_options' => $this->describeOptions((string) $request->getData('selected_options'))
        ];
    }

    private function describeOptions(string $json): string
    {
        if ($json === '') {
            return '';
        }
        try {
            $options = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return (string) __('General product enquiry');
        }
        if (!is_array($options)) {
            return '';
        }
        if (($options['inquiry_scope'] ?? '') === 'parent_only') {
            return (string) __('General enquiry about the parent product; configuration has not been selected.');
        }
        $parts = [];
        foreach ($options as $option) {
            if (is_array($option) && is_string($option['label'] ?? null) && is_string($option['value'] ?? null)) {
                $parts[] = $option['label'] . ': ' . $option['value'];
            }
        }
        return implode('; ', $parts);
    }
}
