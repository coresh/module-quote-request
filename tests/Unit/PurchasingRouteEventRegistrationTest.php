<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Observer\EnforcePurchasingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** XML registration contracts; native Magento conversion and dispatch use a separate probe. */
class PurchasingRouteEventRegistrationTest extends TestCase
{
    private function configuration(): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(dirname(__DIR__, 2) . '/etc/frontend/events.xml', LIBXML_NONET));
        return new \DOMXPath($document);
    }

    public function testLegacyGlobalRegistrationIsExplicitlyDisabled(): void
    {
        $xpath = $this->configuration();
        $legacy = $xpath->query('/config/event[@name="controller_action_predispatch"]'
            . '/observer[@name="coresh_quote_request_block_purchase_routes"]');
        self::assertSame(1, $legacy->length);
        self::assertSame('true', $legacy->item(0)->getAttribute('disabled'));
        self::assertFalse($legacy->item(0)->hasAttribute('instance'));
        self::assertSame(0, $xpath->query('/config/event[@name="controller_action_predispatch"]'
            . '/observer[not(@disabled="true")]')->length);
    }

    public function testActivePolicyRegistrationsCoverExactlyThePurchasingRoutes(): void
    {
        $xpath = $this->configuration();
        $events = [];
        $names = [];
        foreach ($xpath->query('/config/event/observer[@instance="' . EnforcePurchasingPolicy::class . '"]') as $observer) {
            self::assertFalse($observer->hasAttribute('disabled'));
            $events[] = $observer->parentNode->getAttribute('name');
            $names[] = $observer->getAttribute('name');
        }
        sort($events);
        self::assertSame([
            'controller_action_predispatch_checkout',
            'controller_action_predispatch_multishipping',
            'controller_action_predispatch_paypal',
        ], $events);
        self::assertCount(3, array_unique($names));
        self::assertNotContains('coresh_quote_request_block_purchase_routes', $names);
    }

    #[DataProvider('purchasingRoutes')]
    public function testEachPurchasingRouteHasOneActivePolicyObserver(string $route): void
    {
        $xpath = $this->configuration();
        $observers = $xpath->query('/config/event[@name="controller_action_predispatch_' . $route . '"]'
            . '/observer[not(@disabled="true")]');
        self::assertSame(1, $observers->length);
        self::assertSame(EnforcePurchasingPolicy::class, $observers->item(0)->getAttribute('instance'));
    }

    public static function purchasingRoutes(): array
    {
        return [['checkout'], ['multishipping'], ['paypal']];
    }

    #[DataProvider('nonPurchasingRoutes')]
    public function testQuoteAndOtherNonPurchasingActionsHaveNoActivePurchaseObserver(string $route, string $action): void
    {
        $xpath = $this->configuration();
        foreach (['controller_action_predispatch', 'controller_action_predispatch_' . $route,
            'controller_action_predispatch_' . $action] as $event) {
            self::assertSame(0, $xpath->query('/config/event[@name="' . $event . '"]'
                . '/observer[not(@disabled="true")]')->length, $event);
        }
    }

    public static function nonPurchasingRoutes(): array
    {
        return [
            'quote submission' => ['quoterequest', 'quoterequest_request_submit'],
            'native product report' => ['reports', 'reports_report_product_view'],
            'customer messages' => ['customer', 'customer_section_load'],
            'customer account' => ['customer', 'customer_account_index'],
            'product details' => ['catalog', 'catalog_product_view'],
            'CMS page' => ['cms', 'cms_index_index'],
            'contact form' => ['contact', 'contact_index_post'],
            'catalogue search' => ['catalogsearch', 'catalogsearch_result_index'],
        ];
    }

    public function testProductFlagAndCatalogueLayoutHooksRemainRegistered(): void
    {
        $xpath = $this->configuration();
        self::assertSame('Coresh\\QuoteRequest\\Observer\\AddProductListAttribute', $xpath->evaluate(
            'string(/config/event[@name="catalog_block_product_list_collection"]'
            . '/observer[@name="coresh_quote_request_product_flag"]/@instance)'));
        self::assertSame('Coresh\\QuoteRequest\\Observer\\ApplyCatalogueLayout', $xpath->evaluate(
            'string(/config/event[@name="layout_generate_blocks_after"]'
            . '/observer[@name="coresh_quote_request_catalogue_layout"]/@instance)'));
    }
}
