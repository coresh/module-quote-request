<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Configuration contracts; native Magento metadata merging and Field preparation are probed separately. */
class ProductFormFieldConfigurationTest extends TestCase
{
    private function configuration(): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(
            dirname(__DIR__, 2) . '/view/adminhtml/ui_component/product_form.xml',
            LIBXML_NONET
        ));
        return new \DOMXPath($document);
    }

    #[DataProvider('attributeFields')]
    public function testNoticeOverridesUseTheNativeEavContainerPath(string $code, string $formElement): void
    {
        $xpath = $this->configuration();
        $allFields = $xpath->query('/form//field[@name="' . $code . '"]');
        self::assertSame(1, $allFields->length, 'A product attribute must not create duplicate UI fields.');
        $field = $xpath->query('/form/fieldset[@name="quote-request"]'
            . '/container[@name="container_' . $code . '"]/field[@name="' . $code . '"]');
        self::assertSame(1, $field->length, 'Catalog Eav generates container_<code>/children/<code>.');
        self::assertSame($formElement, $field->item(0)->getAttribute('formElement'));
    }

    #[DataProvider('attributeFields')]
    public function testFormElementIsExplicitlyUsableForNativeFieldPreparation(
        string $code,
        string $formElement
    ): void {
        $xpath = $this->configuration();
        self::assertSame($formElement, $xpath->evaluate('string(/form//field[@name="'
            . $code . '"]/@formElement)'));
        self::assertContains($formElement, ['input', 'select']);
    }

    #[DataProvider('attributeFields')]
    public function testNativeEavOptionsAndStoreScopeAreNotReplacedByStaticXml(
        string $code,
        string $formElement
    ): void {
        $xpath = $this->configuration();
        $fieldPath = '/form//field[@name="' . $code . '"]';
        foreach (['options', 'dataScope', 'scopeLabel', 'label', 'default', 'dataType', 'validation'] as $setting) {
            self::assertSame(0, $xpath->query($fieldPath . '/settings/' . $setting)->length,
                'Keep ' . $setting . ' from the product EAV metadata.');
        }
        self::assertSame(0, $xpath->query($fieldPath . '/argument | ' . $fieldPath . '/formElements')->length);
        $notice = $xpath->query($fieldPath . '/settings/notice');
        self::assertSame(1, $notice->length);
        self::assertSame('true', $notice->item(0)->getAttribute('translate'));
        self::assertNotSame('', trim($notice->item(0)->textContent));
    }

    public function testNoUnboundFieldsAreAddedDirectlyToTheEavFieldset(): void
    {
        $xpath = $this->configuration();
        self::assertSame(0, $xpath->query('/form/fieldset[@name="quote-request"]/field')->length,
            'Direct fields do not merge with native container children and bypass EAV options and scope metadata.');
        self::assertSame(4, $xpath->query('/form/fieldset[@name="quote-request"]/container')->length);
        self::assertSame(0, $xpath->query('/form//field[@name="coresh_quote_request_enabled"]')->length,
            'The enabled flag is already supplied by native product EAV metadata.');
    }

    public static function attributeFields(): array
    {
        return [
            'label mode select' => ['coresh_quote_request_label_mode', 'select'],
            'button label input' => ['coresh_quote_request_button_label', 'input'],
            'cart tri-state select' => ['coresh_quote_request_allow_cart', 'select'],
            'price tri-state select' => ['coresh_quote_request_show_price', 'select'],
        ];
    }
}
