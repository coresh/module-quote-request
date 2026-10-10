<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Service;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Model\Email\CustomerConfirmationSender;
use Coresh\QuoteRequest\Model\Product\ProductContextResolver;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Coresh\QuoteRequest\Model\Security\RateLimiter;
use Coresh\QuoteRequest\Model\Validation\QuoteRequestValidator;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SubmitQuoteRequest
{
    public function __construct(
        private readonly Config $config,
        private readonly QuoteRequestValidator $validator,
        private readonly ProductContextResolver $products,
        private readonly QuoteRequestFactory $requestFactory,
        private readonly QuoteRequestResource $resource,
        private readonly Session $customerSession,
        private readonly StoreManagerInterface $stores,
        private readonly Http $httpRequest,
        private readonly RateLimiter $rateLimiter,
        private readonly AdminNotificationSender $adminSender,
        private readonly CustomerConfirmationSender $customerSender,
        private readonly LoggerInterface $logger
    ) {
    }

    public function submit(array $input): array
    {
        $store = $this->stores->getStore();
        $storeId = (int) $store->getId();
        if ($storeId < 1 || !$store->isActive() || !$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('Quote requests are currently unavailable.'));
        }
        $data = $this->validator->validate($input, $storeId);
        $loggedIn = $this->customerSession->isLoggedIn();
        $customerId = $loggedIn ? (int) $this->customerSession->getCustomerId() : null;
        $groupId = $loggedIn ? (int) $this->customerSession->getCustomerGroupId() : Group::NOT_LOGGED_IN_ID;
        $privateIdentity = (string) $this->customerSession->getData('coresh_quote_request_identity');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $privateIdentity)) {
            $privateIdentity = bin2hex(random_bytes(32));
            $this->customerSession->setData('coresh_quote_request_identity', $privateIdentity);
        }
        $canonical = $data;
        unset($canonical['request_token']);
        ksort($canonical['super_attribute']);
        $fingerprint = hash('sha256', json_encode([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'session_identity' => $privateIdentity,
            'fields' => $canonical
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $key = hash('sha256', $data['request_token']);
        $existing = $this->resource->findByIdempotencyKey($key);
        if ($existing !== false) {
            return $this->duplicateResponse($existing, $fingerprint);
        }
        $this->rateLimiter->consume($storeId, (string) $this->httpRequest->getServer('REMOTE_ADDR', ''));
        $product = $this->products->resolve($data['product_id'], $storeId, $groupId, $data['super_attribute']);
        $this->products->assertQuantityAllowed($product, $data['quantity'], $storeId);
        $request = $this->requestFactory->create();
        // Explicit trusted fields only; do not setData($input) or getParams().
        $request->setData(array_merge($product, [
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'quantity' => $data['quantity'],
            'quantity_origin' => 'customer',
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_telephone' => $data['customer_telephone'] ?: null,
            'message' => $data['message'],
            'request_status' => 'new',
            'email_status' => $this->config->isFlag('email/enabled', $storeId) ? 'pending' : 'disabled',
            'email_attempts' => 0,
            'idempotency_key' => $key,
            'payload_hash' => $fingerprint
        ]));
        try {
            $this->resource->save($request);
        } catch (\Exception $exception) {
            // A concurrent insert can win the unique index. Only a verified payload is acknowledged.
            $existing = $this->resource->findByIdempotencyKey($key);
            if ($existing !== false) {
                return $this->duplicateResponse($existing, $fingerprint);
            }
            throw $exception;
        }
        try {
            $this->adminSender->sendNotification($request);
        } catch (\Throwable $exception) {
            // Persistence succeeded; transport failure must never erase or duplicate the enquiry.
            $this->logger->warning('Quote request notification failed.', [
                'request_id' => (int) $request->getId(),
                'error_code' => 'notification_failed'
            ]);
        }
        try {
            $this->customerSender->sendConfirmation($request);
        } catch (\Throwable $exception) {
            $this->logger->warning('Quote request confirmation failed.', [
                'request_id' => (int) $request->getId(),
                'error_code' => 'confirmation_failed'
            ]);
        }
        return [
            'success' => true,
            'status' => 'accepted',
            'message' => (string) __('Your quote request has been received.'),
            'request_id' => (int) $request->getId()
        ];
    }

    private function duplicateResponse(array $existing, string $fingerprint): array
    {
        if (!hash_equals((string) $existing['payload_hash'], $fingerprint)) {
            throw new LocalizedException(__('This request token is no longer valid. Please refresh the page and try again.'));
        }
        return [
            'success' => true,
            'status' => 'duplicate',
            'message' => (string) __('Your quote request has already been received.'),
            'request_id' => (int) $existing['request_id']
        ];
    }
}
