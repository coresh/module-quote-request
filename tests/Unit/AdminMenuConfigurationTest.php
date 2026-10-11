<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Menu-to-controller authorization contracts; native menu filtering uses a separate probe. */
class AdminMenuConfigurationTest extends TestCase
{
    private function configuration(string $relativeFile): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(dirname(__DIR__, 2) . '/' . $relativeFile, LIBXML_NONET));
        return new \DOMXPath($document);
    }

    private function controllerResource(string $relativeFile): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Controller/Adminhtml/' . $relativeFile);
        self::assertIsString($source);
        self::assertSame(1, preg_match('/^\s*public\s+const\s+ADMIN_RESOURCE\s*=\s*[\'"]([^\'"]+)[\'"];\s*$/m', $source, $matches));
        return $matches[1];
    }

    public function testRequestMenuAppearsOnceUnderSalesAndKeepsTheProtectedListingRoute(): void
    {
        $xpath = $this->configuration('etc/adminhtml/menu.xml');
        $items = $xpath->query('/config/menu/add[@id="Coresh_QuoteRequest::requests"]');
        self::assertSame(1, $items->length);
        $item = $items->item(0);
        self::assertSame('Magento_Sales::sales', $item->getAttribute('parent'));
        self::assertSame('Coresh — Quote Requests', $item->getAttribute('title'));
        self::assertSame('Coresh_QuoteRequest', $item->getAttribute('module'));
        self::assertSame('coresh_quoterequest/request/index', $item->getAttribute('action'));
        self::assertSame($this->controllerResource('Request/Index.php'), $item->getAttribute('resource'));
        self::assertSame('Coresh_QuoteRequest::view', $item->getAttribute('resource'));
        self::assertSame(0, $xpath->query('/config/menu/add[@id="Coresh_QuoteRequest::requests"]'
            . '[@parent="Magento_Customer::customer"]')->length);
        self::assertSame(0, $xpath->query('/config/menu/add[starts-with(@id,"Magento_Sales::")]')->length,
            'The extension must not override native Sales menu resources.');
    }

    public function testMigrationKeepsItsSeparateLocationAndPermission(): void
    {
        $xpath = $this->configuration('etc/adminhtml/menu.xml');
        $items = $xpath->query('/config/menu/add[@id="Coresh_QuoteRequest::migration"]');
        self::assertSame(1, $items->length);
        $item = $items->item(0);
        self::assertSame('Magento_Customer::customer', $item->getAttribute('parent'));
        self::assertSame('coresh_quoterequest/migration/index', $item->getAttribute('action'));
        self::assertSame($this->controllerResource('Migration/Index.php'), $item->getAttribute('resource'));
        self::assertSame('Coresh_QuoteRequest::migrate', $item->getAttribute('resource'));
    }

    public function testBothListingAuthorizationDeclarationsUseTheSameResourceAsTheMenu(): void
    {
        $menu = $this->configuration('etc/adminhtml/menu.xml');
        $resource = $menu->evaluate('string(/config/menu/add[@id="Coresh_QuoteRequest::requests"]/@resource)');
        $listing = $this->configuration('view/adminhtml/ui_component/coresh_quote_request_listing.xml');
        self::assertSame($resource, $listing->evaluate('string(/listing/dataSource/aclResource)'));
        self::assertSame($resource, $listing->evaluate('string(/listing/dataSource/dataProvider/argument[@name="data"]'
            . '/item[@name="config"]/item[@name="aclResource"])'));
        self::assertSame($resource, $this->controllerResource('Request/View.php'));
        self::assertSame($resource, $this->controllerResource('Request/ExportCsv.php'));
    }

    #[DataProvider('protectedActions')]
    public function testAdminActionsRemainBoundToExplicitLeafPermissions(string $controller, string $resource): void
    {
        self::assertSame($resource, $this->controllerResource($controller));
        $acl = $this->configuration('etc/acl.xml');
        $nodes = $acl->query('/config/acl/resources/resource[@id="Magento_Backend::admin"]'
            . '//resource[@id="' . $resource . '"]');
        self::assertSame(1, $nodes->length, 'Each controller resource must be declared exactly once.');
        self::assertSame('Coresh_QuoteRequest::root', $nodes->item(0)->parentNode->getAttribute('id'));
        self::assertSame(0, $acl->query('//resource[@id="' . $resource . '"]/resource')->length,
            'Granting a leaf permission must not inherit unrelated administrative operations.');
    }

    public static function protectedActions(): array
    {
        return [
            'listing' => ['Request/Index.php', 'Coresh_QuoteRequest::view'],
            'request details' => ['Request/View.php', 'Coresh_QuoteRequest::view'],
            'CSV export' => ['Request/ExportCsv.php', 'Coresh_QuoteRequest::view'],
            'single status change' => ['Request/SaveStatus.php', 'Coresh_QuoteRequest::manage'],
            'batch status change' => ['Request/MassStatus.php', 'Coresh_QuoteRequest::manage'],
            'notification email' => ['Request/ResendEmail.php', 'Coresh_QuoteRequest::send_email'],
            'migration page' => ['Migration/Index.php', 'Coresh_QuoteRequest::migrate'],
            'migration execution' => ['Migration/Run.php', 'Coresh_QuoteRequest::migrate'],
        ];
    }

    public function testNativeSalesParentHasAnExplicitPackageAndModuleDependency(): void
    {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('magento/module-sales', $composer['require']);
        self::assertIsString($composer['require']['magento/module-sales']);
        self::assertNotSame('', $composer['require']['magento/module-sales']);
        $module = $this->configuration('etc/module.xml');
        self::assertSame(1, $module->query('/config/module[@name="Coresh_QuoteRequest"]/sequence'
            . '/module[@name="Magento_Sales"]')->length);
    }

    public function testListingAndDetailsKeepTheirActiveMenuIdentifierAfterTheMove(): void
    {
        foreach (['Request/Index.php', 'Request/View.php'] as $controller) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/Controller/Adminhtml/' . $controller);
            self::assertStringContainsString("setActiveMenu('Coresh_QuoteRequest::requests')", $source);
        }
    }
}
