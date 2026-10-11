<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

require_once dirname(__DIR__) . '/Fixtures/route_observer_framework.php';

use Coresh\QuoteRequest\Model\Catalog\PurchasingPolicy;
use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Observer\BlockPurchasingRoutes;
use Coresh\QuoteRequest\Observer\EnforcePurchasingPolicy;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockPurchasingRoutesTest extends TestCase
{
    private PurchasingPolicy&MockObject $policy;
    private CheckoutSession&MockObject $checkoutSession;
    private ActionFlag&MockObject $actionFlag;
    private RedirectInterface&MockObject $redirect;
    private UrlInterface&MockObject $url;
    private ManagerInterface&MockObject $messageManager;
    private HttpResponse&MockObject $response;

    protected function setUp(): void
    {
        $this->policy = $this->createMock(PurchasingPolicy::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->actionFlag = $this->createMock(ActionFlag::class);
        $this->redirect = $this->createMock(RedirectInterface::class);
        $this->url = $this->createMock(UrlInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->response = $this->createMock(HttpResponse::class);
        $this->response->method('setNoCacheHeaders')->willReturnSelf();
        $this->response->method('setHttpResponseCode')->willReturnSelf();
        $this->response->method('setHeader')->willReturnSelf();
        $this->response->method('setBody')->willReturnSelf();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonPurchaseActions')]
    public function testModernNonPurchaseActionNeedsNoControllerRequestOrResponse(string $route, string $action): void
    {
        $request = $this->request($route, $action);
        $controller = new class implements HttpPostActionInterface {
            public function execute() { throw new \LogicException('Observer must not execute a controller.'); }
        };
        self::assertFalse(method_exists($controller, 'getRequest'));
        self::assertFalse(method_exists($controller, 'getResponse'));
        $this->expectNoPurchasingSideEffects();
        $this->observer($request)->execute($this->event($request, $controller));
    }

    public static function nonPurchaseActions(): array
    {
        return [
            'quote submission' => ['quoterequest', 'quoterequest_request_submit'],
            'private request report' => ['quoterequest', 'quoterequest_reports_view'],
            'product page' => ['catalog', 'catalog_product_view'],
            'customer account' => ['customer', 'customer_account_index'],
            'CMS page' => ['cms', 'cms_index_index'],
            'contact form' => ['contact', 'contact_index_post'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pastOrderActions')]
    public function testCheckoutSuccessAndFailureDoNotLoadCartOrEnforcePurchasing(string $action): void
    {
        $request = $this->request('checkout', $action);
        $this->expectNoPurchasingSideEffects();
        $this->observer($request)->execute($this->event($request));
    }

    public static function pastOrderActions(): array
    {
        return [['checkout_onepage_success'], ['checkout_onepage_failure']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allowedPurchaseActions')]
    public function testAllowedPurchasingChecksCurrentAndExistingQuoteStore(string $route, string $action, mixed $quoteId, ?int $quoteStore): void
    {
        $request = $this->request($route, $action);
        $seen = [];
        $this->policy->expects($quoteId ? self::exactly(2) : self::once())->method('assertAllowed')
            ->willReturnCallback(static function (?int $storeId = null) use (&$seen): void { $seen[] = $storeId; });
        $this->checkoutSession->expects(self::once())->method('getQuoteId')->willReturn($quoteId);
        if ($quoteId) {
            $quote = $this->createMock(CartInterface::class);
            $quote->expects(self::once())->method('getStoreId')->willReturn($quoteStore);
            $this->checkoutSession->expects(self::once())->method('getQuote')->willReturn($quote);
        } else {
            $this->checkoutSession->expects(self::never())->method('getQuote');
        }
        $this->expectNoDenialSideEffects();
        $this->observer($request)->execute($this->event($request));
        self::assertSame($quoteId ? [null, $quoteStore] : [null], $seen);
    }

    public static function allowedPurchaseActions(): array
    {
        return [
            'cart without existing quote' => ['checkout', 'checkout_cart_add', null, null],
            'checkout without quote' => ['checkout', 'checkout_index_index', 0, null],
            'checkout with saved quote' => ['checkout', 'checkout_index_index', 123, 2],
            'multishipping with saved quote' => ['multishipping', 'multishipping_checkout_addresses', '123', 3],
            'PayPal with saved quote' => ['paypal', 'paypal_express_start', 123, 4],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedPurchaseActions')]
    public function testAjaxDenialReturnsSafeJsonOnSharedResponseAndStopsDispatch(string $route, string $action, bool $savedStoreBlocked): void
    {
        $request = $this->request($route, $action, true);
        $this->configureDenial($savedStoreBlocked);
        $this->actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->expects(self::once())->method('setNoCacheHeaders');
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(403);
        $this->response->expects(self::once())->method('setHeader')->with('Content-Type', 'application/json', true);
        $this->response->expects(self::once())->method('setBody')->with(self::callback(static function (string $body): bool {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return $data === ['success' => false, 'message' => 'Online purchasing is unavailable. Please request a quote.']
                && !str_contains($body, 'private-policy-detail');
        }));
        $this->redirect->expects(self::never())->method('redirect');
        $this->messageManager->expects(self::never())->method('addErrorMessage');
        $this->observer($request)->execute($this->event($request));
    }

    public static function blockedPurchaseActions(): array
    {
        return [
            'cart blocked in current store' => ['checkout', 'checkout_cart_add', false],
            'checkout blocked in saved quote store' => ['checkout', 'checkout_index_index', true],
            'multishipping blocked in current store' => ['multishipping', 'multishipping_checkout_addresses', false],
            'multishipping blocked in quote store' => ['multishipping', 'multishipping_checkout_addresses', true],
            'PayPal blocked in current store' => ['paypal', 'paypal_express_start', false],
            'PayPal blocked in quote store' => ['paypal', 'paypal_express_start', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedPurchaseActions')]
    public function testNormalDenialAddsErrorAndRedirectsSharedResponse(string $route, string $action, bool $savedStoreBlocked): void
    {
        $request = $this->request($route, $action, false);
        $this->configureDenial($savedStoreBlocked, 'Online purchasing is currently unavailable. Please request a quote.');
        $this->actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->expects(self::once())->method('setNoCacheHeaders');
        $this->response->expects(self::never())->method('setHttpResponseCode');
        $this->response->expects(self::never())->method('setHeader');
        $this->response->expects(self::never())->method('setBody');
        $this->messageManager->expects(self::once())->method('addErrorMessage')
            ->with('Online purchasing is currently unavailable. Please request a quote.');
        $this->url->expects(self::once())->method('getUrl')->with('')->willReturn('https://store.example.test/');
        $this->redirect->expects(self::once())->method('redirect')->with($this->response, 'https://store.example.test/');
        $this->observer($request)->execute($this->event($request));
    }

    public function testEventRequestIsPreferredOverInjectedFallback(): void
    {
        $fallback = $this->request('checkout', 'checkout_cart_add');
        $fallback->expects(self::never())->method('getRouteName');
        $actual = $this->request('quoterequest', 'quoterequest_request_submit');
        $actual->expects(self::once())->method('getRouteName');
        $this->expectNoPurchasingSideEffects();
        $this->observer($fallback)->execute($this->event($actual));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidEventRequests')]
    public function testMissingOrInvalidEventRequestUsesInjectedHttp(mixed $eventRequest): void
    {
        $request = $this->request('quoterequest', 'quoterequest_request_submit');
        $request->expects(self::once())->method('getRouteName');
        $this->expectNoPurchasingSideEffects();
        $this->observer($request)->execute($this->event($eventRequest));
    }

    public static function invalidEventRequests(): array
    {
        return ['missing' => [null], 'wrong object' => [new \stdClass()], 'wrong scalar' => ['request']];
    }

    public function testControllerGetterMethodsAreNeverUsedEvenForDeniedLegacyController(): void
    {
        $request = $this->request('checkout', 'checkout_cart_add', true);
        $controller = new class {
            public function getRequest() { throw new \LogicException('Legacy request getter must not be used.'); }
            public function getResponse() { throw new \LogicException('Legacy response getter must not be used.'); }
        };
        $this->configureDenial(false);
        $this->actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(403);
        $this->observer($request)->execute($this->event($request, $controller));
    }

    public function testUnexpectedPolicyFailurePropagatesInsteadOfAuthorizingPurchase(): void
    {
        $request = $this->request('checkout', 'checkout_cart_add');
        $this->policy->expects(self::once())->method('assertAllowed')->willThrowException(new \RuntimeException('Policy service unavailable.'));
        $this->checkoutSession->expects(self::never())->method('getQuoteId');
        $this->expectNoDenialSideEffects();
        $this->expectException(\RuntimeException::class);
        $this->observer($request)->execute($this->event($request));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('storeSwitchCases')]
    public function testRealPurchasingPolicyPreventsCurrentOrSavedStoreBypass(int $currentStore, ?int $quoteStore, array $blockedStores, bool $shouldDeny): void
    {
        $request = $this->request('checkout', 'checkout_cart_add', true);
        $config = $this->createMock(Config::class);
        $config->method('isPurchasingDisabled')->willReturnCallback(static fn (int $storeId): bool => in_array($storeId, $blockedStores, true));
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn('frontend');
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($currentStore);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $policy = new PurchasingPolicy($config, $stores, $state);
        if (in_array($currentStore, $blockedStores, true)) {
            $this->checkoutSession->expects(self::never())->method('getQuoteId');
            $this->checkoutSession->expects(self::never())->method('getQuote');
        } elseif ($quoteStore !== null) {
            $quote = $this->createMock(CartInterface::class);
            $quote->method('getStoreId')->willReturn($quoteStore);
            $this->checkoutSession->expects(self::once())->method('getQuoteId')->willReturn(123);
            $this->checkoutSession->expects(self::once())->method('getQuote')->willReturn($quote);
        } else {
            $this->checkoutSession->expects(self::once())->method('getQuoteId')->willReturn(null);
            $this->checkoutSession->expects(self::never())->method('getQuote');
        }
        if ($shouldDeny) {
            $this->actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
            $this->response->expects(self::once())->method('setNoCacheHeaders');
            $this->response->expects(self::once())->method('setHttpResponseCode')->with(403);
        } else {
            $this->expectNoDenialSideEffects();
        }
        (new EnforcePurchasingPolicy($policy, $this->checkoutSession, $this->actionFlag,
            $this->redirect, $this->url, $this->messageManager, $request, $this->response))->execute($this->event($request));
    }

    public static function storeSwitchCases(): array
    {
        return [
            'blocked current store without quote' => [1, null, [1], true],
            'blocked current store with allowed quote' => [1, 2, [1], true],
            'allowed current store with blocked quote' => [2, 1, [1], true],
            'both stores blocked' => [1, 2, [1, 2], true],
            'both stores allowed' => [2, 3, [1], false],
            'allowed current store without quote' => [2, null, [1], false],
        ];
    }

    public function testLegacyObserverIsAnInheritedCompatibilityShim(): void
    {
        $legacy = new \ReflectionClass(BlockPurchasingRoutes::class);
        self::assertSame(EnforcePurchasingPolicy::class, $legacy->getParentClass()->getName());
        self::assertSame(EnforcePurchasingPolicy::class, $legacy->getMethod('execute')->getDeclaringClass()->getName());
        self::assertSame(EnforcePurchasingPolicy::class, $legacy->getConstructor()->getDeclaringClass()->getName());
        self::assertSame([], array_values(array_filter($legacy->getMethods(),
            static fn (\ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === BlockPurchasingRoutes::class)));
        self::assertSame([], array_values(array_filter($legacy->getProperties(),
            static fn (\ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === BlockPurchasingRoutes::class)));
    }

    public function testLegacyShimIgnoresQuoteSubmissionWithoutControllerGetters(): void
    {
        $request = $this->request('quoterequest', 'quoterequest_request_submit');
        $controller = new class implements HttpPostActionInterface {
            public function execute() { throw new \LogicException('Observer must not execute a controller.'); }
        };
        self::assertFalse(method_exists($controller, 'getRequest'));
        self::assertFalse(method_exists($controller, 'getResponse'));
        $this->expectNoPurchasingSideEffects();
        (new BlockPurchasingRoutes($this->policy, $this->checkoutSession, $this->actionFlag,
            $this->redirect, $this->url, $this->messageManager, $request, $this->response))
            ->execute($this->event($request, $controller));
    }

    public function testLegacyShimStillDeniesPurchasingOnTheSharedResponse(): void
    {
        $request = $this->request('checkout', 'checkout_cart_add', true);
        $this->configureDenial(false);
        $this->actionFlag->expects(self::once())->method('set')->with('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->expects(self::once())->method('setNoCacheHeaders');
        $this->response->expects(self::once())->method('setHttpResponseCode')->with(403);
        $this->response->expects(self::once())->method('setHeader')->with('Content-Type', 'application/json', true);
        $this->response->expects(self::once())->method('setBody')->with(
            '{"success":false,"message":"Online purchasing is unavailable. Please request a quote."}');
        $this->redirect->expects(self::never())->method('redirect');
        $this->messageManager->expects(self::never())->method('addErrorMessage');
        (new BlockPurchasingRoutes($this->policy, $this->checkoutSession, $this->actionFlag,
            $this->redirect, $this->url, $this->messageManager, $request, $this->response))
            ->execute($this->event($request));
    }

    private function configureDenial(bool $savedStoreBlocked, string $message = 'private-policy-detail'): void
    {
        $this->policy->expects($savedStoreBlocked ? self::exactly(2) : self::once())->method('assertAllowed')
            ->willReturnCallback(static function (?int $storeId = null) use ($savedStoreBlocked, $message): void {
                if (!$savedStoreBlocked || $storeId === 1) {
                    throw new LocalizedException(__($message));
                }
            });
        if ($savedStoreBlocked) {
            $quote = $this->createMock(CartInterface::class);
            $quote->expects(self::once())->method('getStoreId')->willReturn(1);
            $this->checkoutSession->expects(self::once())->method('getQuoteId')->willReturn(123);
            $this->checkoutSession->expects(self::once())->method('getQuote')->willReturn($quote);
        } else {
            $this->checkoutSession->expects(self::never())->method('getQuoteId');
            $this->checkoutSession->expects(self::never())->method('getQuote');
        }
    }

    private function expectNoPurchasingSideEffects(): void
    {
        $this->policy->expects(self::never())->method('assertAllowed');
        $this->checkoutSession->expects(self::never())->method('getQuoteId');
        $this->checkoutSession->expects(self::never())->method('getQuote');
        $this->expectNoDenialSideEffects();
    }

    private function expectNoDenialSideEffects(): void
    {
        $this->actionFlag->expects(self::never())->method('set');
        $this->response->expects(self::never())->method('setNoCacheHeaders');
        $this->response->expects(self::never())->method('setBody');
        $this->redirect->expects(self::never())->method('redirect');
        $this->messageManager->expects(self::never())->method('addErrorMessage');
    }

    private function observer(Http $request): EnforcePurchasingPolicy
    {
        return new EnforcePurchasingPolicy($this->policy, $this->checkoutSession, $this->actionFlag,
            $this->redirect, $this->url, $this->messageManager, $request, $this->response);
    }

    private function request(string $route, string $action, bool $ajax = false): Http&MockObject
    {
        $methods = ['getRouteName', 'getFullActionName', 'isXmlHttpRequest'];
        $present = array_values(array_filter($methods, static fn (string $method): bool => method_exists(Http::class, $method)));
        $absent = array_values(array_diff($methods, $present));
        $builder = $this->getMockBuilder(Http::class)->disableOriginalConstructor();
        if ($present !== []) { $builder->onlyMethods($present); }
        if ($absent !== []) { $builder->addMethods($absent); }
        $request = $builder->getMock();
        $request->method('getRouteName')->willReturn($route);
        $request->method('getFullActionName')->willReturn($action);
        $request->method('isXmlHttpRequest')->willReturn($ajax);
        return $request;
    }

    private function event(mixed $request, ?object $controller = null): Observer
    {
        return new Observer(['event' => new Event(['request' => $request, 'controller_action' => $controller ?? new \stdClass()])]);
    }
}
