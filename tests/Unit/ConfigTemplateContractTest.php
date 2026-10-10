<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** File contracts for the native Email Template source; no Magento form is bootstrapped. */
class ConfigTemplateContractTest extends TestCase
{
    private function xml(string $file): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(dirname(__DIR__, 2) . '/' . $file, LIBXML_NONET));
        return new \DOMXPath($document);
    }

    public static function emailFields(): array
    {
        return [
            ['admin_template', 'coresh_quote_request_admin_notification'],
            ['customer_template', 'coresh_quote_request_customer_confirmation']
        ];
    }

    #[DataProvider('emailFields')]
    public function testNativeDefaultOptionIdentifierIsRegistered(string $field, string $legacy): void
    {
        $system = $this->xml('etc/adminhtml/system.xml');
        $node = $system->query('/config/system/section[@id="coresh_quote_request"]/group[@id="email"]/field[@id="' . $field . '"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        self::assertSame('Magento\\Config\\Model\\Config\\Source\\Email\\Template', trim($system->evaluate('string(source_model)', $node)));
        $path = trim($system->evaluate('string(config_path)', $node)) ?: 'coresh_quote_request/email/' . $field;
        $templates = $this->xml('etc/email_templates.xml');
        $default = str_replace('/', '_', $path);
        self::assertSame(1, $templates->query('/config/template[@id="' . $default . '"]')->length);
        self::assertNotSame($legacy, $default);
    }

    #[DataProvider('emailFields')]
    public function testNewDefaultValueIsTheSelectableNativeOption(string $field, string $legacy): void
    {
        $defaults = $this->xml('etc/config.xml');
        self::assertSame('coresh_quote_request_email_' . $field, trim($defaults->evaluate('string(/config/default/coresh_quote_request/email/' . $field . ')')));
    }

    #[DataProvider('emailFields')]
    public function testStoredLegacyIdentifierRemainsAnEquivalentUsableTemplate(string $field, string $legacy): void
    {
        $templates = $this->xml('etc/email_templates.xml');
        $canonical = $templates->query('/config/template[@id="coresh_quote_request_email_' . $field . '"]')->item(0);
        $alias = $templates->query('/config/template[@id="' . $legacy . '"]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $canonical);
        self::assertInstanceOf(\DOMElement::class, $alias);
        foreach (['file', 'type', 'module', 'area', 'label'] as $attribute) {
            self::assertSame($canonical->getAttribute($attribute), $alias->getAttribute($attribute));
        }
        self::assertSame('Coresh_QuoteRequest', $canonical->getAttribute('module'));
        self::assertSame('frontend', $canonical->getAttribute('area'));
        self::assertSame('html', $canonical->getAttribute('type'));
        self::assertFileIsReadable(dirname(__DIR__, 2) . '/view/frontend/email/' . $canonical->getAttribute('file'));
    }
}
