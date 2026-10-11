<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use PHPUnit\Framework\TestCase;

/** Configuration contracts; native stage merging and factory execution use a separate probe. */
class GridCollectionFactoryIsolationTest extends TestCase
{
    private function configuration(string $relativeFile): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(dirname(__DIR__, 2) . '/' . $relativeFile, LIBXML_NONET));
        return new \DOMXPath($document);
    }

    public function testModuleNeverOverridesTheSharedFactoryCollectionMap(): void
    {
        foreach (['etc/di.xml', 'etc/adminhtml/di.xml', 'etc/frontend/di.xml'] as $file) {
            $xpath = $this->configuration($file);
            self::assertSame(0, $xpath->query('/config/type[@name="Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory"]'
                . '/arguments/argument[@name="collections"]')->length, $file);
        }
    }

    public function testPrivateFactoryContainsTheQuoteRequestHandle(): void
    {
        $xpath = $this->configuration('etc/adminhtml/di.xml');
        self::assertSame('Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory', $xpath->evaluate(
            'string(/config/virtualType[@name="CoreshQuoteRequestGridCollectionFactory"]/@type)'));
        self::assertSame('CoreshQuoteRequestGridCollection', $xpath->evaluate(
            'string(/config/virtualType[@name="CoreshQuoteRequestGridCollectionFactory"]/arguments'
            . '/argument[@name="collections"]/item[@name="coresh_quote_request_listing_data_source"])'));
    }

    public function testGridReportingUsesItsPrivateFactoryAndFilterPool(): void
    {
        $xpath = $this->configuration('etc/adminhtml/di.xml');
        foreach (['collectionFactory' => 'CoreshQuoteRequestGridCollectionFactory',
            'filterPool' => 'CoreshQuoteRequestGridFilterPool'] as $argument => $expected) {
            self::assertSame($expected, $xpath->evaluate(
                'string(/config/virtualType[@name="CoreshQuoteRequestGridReporting"]'
                . '/arguments/argument[@name="' . $argument . '"])'));
        }
    }

    public function testListingDataProviderRoutesThroughPrivateReporting(): void
    {
        $listing = $this->configuration('view/adminhtml/ui_component/coresh_quote_request_listing.xml');
        $provider = $listing->evaluate('string(/listing/dataSource[@name="coresh_quote_request_listing_data_source"]'
            . '/dataProvider/@class)');
        self::assertSame('CoreshQuoteRequestGridDataProvider', $provider);
        $di = $this->configuration('etc/adminhtml/di.xml');
        self::assertSame('CoreshQuoteRequestGridReporting', $di->evaluate(
            'string(/config/virtualType[@name="' . $provider . '"]/arguments/argument[@name="reporting"])'));
    }
}
