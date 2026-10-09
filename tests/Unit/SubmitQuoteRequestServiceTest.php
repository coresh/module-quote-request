<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Model\Email\CustomerConfirmationSender;
use Coresh\QuoteRequest\Model\Product\ProductContextResolver;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Coresh\QuoteRequest\Model\Security\RateLimiter;
use Coresh\QuoteRequest\Model\Service\SubmitQuoteRequest;
use Coresh\QuoteRequest\Model\Validation\QuoteRequestValidator;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Exercises the real orchestration with mocked external persistence/catalogue/transports. */
class SubmitQuoteRequestServiceTest extends TestCase
{
    private function fields(): array
    {
        return ['product_id' => 42, 'quantity' => '2.0000', 'customer_name' => 'Customer',
            'customer_email' => 'customer@example.test', 'customer_telephone' => '', 'message' => 'Please quote.',
            'request_token' => str_repeat('c', 64), 'super_attribute' => []];
    }

    private function fingerprint(array $fields, string $identity = ''): string
    {
        unset($fields['request_token']);
        ksort($fields['super_attribute']);
        return hash('sha256', json_encode(['store_id' => 2, 'customer_id' => 9,
            'session_identity' => $identity ?: str_repeat('a', 64), 'fields' => $fields],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function fixture(bool $enabled = true, bool $active = true): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->with(2)->willReturn($enabled);
        $config->method('isFlag')->with('email/enabled', 2)->willReturn(true);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('isActive')->willReturn($active);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $validator = $this->createMock(QuoteRequestValidator::class);
        $validator->method('validate')->willReturn($this->fields());
        $products = $this->createMock(ProductContextResolver::class);
        $products->method('resolve')->willReturn(['product_id' => 42, 'product_name' => 'Trusted product',
            'product_sku' => 'TRUSTED42', 'product_url' => 'https://shop.example.test/p.html',
            'product_type' => 'simple', 'selected_product_id' => null, 'selected_product_sku' => null,
            'selected_options' => null]);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $request = $this->createMock(QuoteRequest::class);
        $request->method('getId')->willReturn(17);
        $request->method('setData')->willReturnSelf();
        $factory->method('create')->willReturn($request);
        $resource = $this->createMock(Resource::class);
        $session = $this->createMock(Session::class);
        $session->method('isLoggedIn')->willReturn(true);
        $session->method('getCustomerId')->willReturn(9);
        $session->method('getCustomerGroupId')->willReturn(3);
        $session->method('getData')->with('coresh_quote_request_identity')->willReturn(str_repeat('a', 64));
        $http = $this->createMock(Http::class);
        $http->method('getServer')->with('REMOTE_ADDR', '')->willReturn('192.0.2.10');
        $limiter = $this->createMock(RateLimiter::class);
        $admin = $this->createMock(AdminNotificationSender::class);
        $customer = $this->createMock(CustomerConfirmationSender::class);
        $logger = $this->createMock(LoggerInterface::class);
        $service = new SubmitQuoteRequest($config, $validator, $products, $factory, $resource, $session,
            $stores, $http, $limiter, $admin, $customer, $logger);
        return compact('config', 'validator', 'products', 'factory', 'request', 'resource', 'session',
            'limiter', 'admin', 'customer', 'logger', 'service');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableStores')]
    public function testDisabledOrInactiveStoreRejectsBeforeValidation(bool $enabled, bool $active): void
    {
        $f = $this->fixture($enabled, $active);
        $f['validator']->expects(self::never())->method('validate');
        $f['resource']->expects(self::never())->method('save');
        $this->expectException(LocalizedException::class);
        $f['service']->submit($this->fields());
    }

    public static function unavailableStores(): array { return [[false, true], [true, false]]; }

    public function testNewRequestUsesTrustedAllowlistAndPersistsBeforeEitherEmail(): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->with(hash('sha256', str_repeat('c', 64)))->willReturn(false);
        $f['limiter']->expects(self::once())->method('consume')->with(2, '192.0.2.10');
        $f['products']->expects(self::once())->method('resolve')->with(42, 2, 3, []);
        $f['products']->expects(self::once())->method('assertQuantityAllowed')->with(self::anything(), '2.0000', 2);
        $f['request']->expects(self::once())->method('setData')->with(self::callback(function (array $data): bool {
            self::assertSame('Trusted product', $data['product_name']);
            self::assertSame('TRUSTED42', $data['product_sku']);
            self::assertSame(9, $data['customer_id']);
            self::assertSame(2, $data['store_id']);
            self::assertSame('new', $data['request_status']);
            self::assertSame('pending', $data['email_status']);
            self::assertSame(0, $data['email_attempts']);
            self::assertSame($this->fingerprint($this->fields()), $data['payload_hash']);
            self::assertArrayNotHasKey('request_token', $data);
            self::assertArrayNotHasKey('untrusted_field', $data);
            return true;
        }));
        $saved = false;
        $f['resource']->expects(self::once())->method('save')->with($f['request'])
            ->willReturnCallback(static function () use (&$saved, $f) { $saved = true; return $f['resource']; });
        $f['admin']->expects(self::once())->method('sendNotification')->willReturnCallback(
            static function () use (&$saved): bool { self::assertTrue($saved); return true; });
        $f['customer']->expects(self::once())->method('sendConfirmation')->willReturnCallback(
            static function () use (&$saved): void { self::assertTrue($saved); });
        $response = $f['service']->submit(array_replace($this->fields(), ['product_name' => 'Spoofed',
            'product_sku' => 'SPOOF', 'store_id' => 8, 'customer_id' => 100, 'email_status' => 'sent',
            'untrusted_field' => 'ignored']));
        self::assertSame(['success' => true, 'status' => 'accepted',
            'message' => 'Your quote request has been received.', 'request_id' => 17], $response);
    }

    public function testOwnedDuplicateSkipsRateCounterCatalogueSaveAndNotifications(): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->willReturn([
            'request_id' => 17, 'payload_hash' => $this->fingerprint($this->fields())]);
        foreach (['limiter' => 'consume', 'products' => 'resolve', 'factory' => 'create',
            'resource' => 'save', 'admin' => 'sendNotification', 'customer' => 'sendConfirmation'] as $key => $method) {
            $f[$key]->expects(self::never())->method($method);
        }
        self::assertSame('duplicate', $f['service']->submit($this->fields())['status']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unownedPayloads')]
    public function testReusedTokenCannotAcknowledgeAnotherSessionOrChangedPayload(string $hash): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->willReturn(['request_id' => 123, 'payload_hash' => $hash]);
        $f['resource']->expects(self::never())->method('save');
        $f['admin']->expects(self::never())->method('sendNotification');
        $this->expectException(LocalizedException::class);
        $f['service']->submit($this->fields());
    }

    public static function unownedPayloads(): array { return [[str_repeat('b', 64)], [''], ['not-a-hash']]; }

    public function testConcurrentInsertWinnerIsVerifiedWithoutSendingAgain(): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->willReturnOnConsecutiveCalls(false,
            ['request_id' => 88, 'payload_hash' => $this->fingerprint($this->fields())]);
        $f['resource']->expects(self::once())->method('save')->willThrowException(new \RuntimeException('duplicate key'));
        $f['admin']->expects(self::never())->method('sendNotification');
        $f['customer']->expects(self::never())->method('sendConfirmation');
        $response = $f['service']->submit($this->fields());
        self::assertSame('duplicate', $response['status']);
        self::assertSame(88, $response['request_id']);
    }

    public function testUnrelatedPersistenceFailureIsNotReportedAsSuccess(): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->willReturn(false);
        $f['resource']->method('save')->willThrowException(new \RuntimeException('storage unavailable'));
        $f['admin']->expects(self::never())->method('sendNotification');
        $f['customer']->expects(self::never())->method('sendConfirmation');
        $this->expectException(\RuntimeException::class);
        $f['service']->submit($this->fields());
    }

    public function testBothTransportFailuresPreserveAcceptedRequestAndRedactLogs(): void
    {
        $f = $this->fixture();
        $f['resource']->method('findByIdempotencyKey')->willReturn(false);
        $f['resource']->expects(self::once())->method('save');
        $f['admin']->method('sendNotification')->willThrowException(new \RuntimeException('SMTP secret customer@example.test'));
        $f['customer']->method('sendConfirmation')->willThrowException(new \RuntimeException('SMTP password'));
        $messages = [];
        $f['logger']->expects(self::exactly(2))->method('warning')->willReturnCallback(
            static function ($message, array $context) use (&$messages): void { $messages[] = [$message, $context]; });
        self::assertSame('accepted', $f['service']->submit($this->fields())['status']);
        self::assertSame([
            ['Quote request notification failed.', ['request_id' => 17, 'error_code' => 'notification_failed']],
            ['Quote request confirmation failed.', ['request_id' => 17, 'error_code' => 'confirmation_failed']]
        ], $messages);
    }
}
