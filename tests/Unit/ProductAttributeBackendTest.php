<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Attribute\Backend\ButtonLabel;
use Coresh\QuoteRequest\Model\Attribute\Backend\InheritYesNo;
use Coresh\QuoteRequest\Model\Attribute\Backend\LabelMode;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class ProductAttributeBackendTest extends TestCase
{
    public function testUseDefaultNullAndCustomBlankAreDistinct(): void
    {
        self::assertNull((new LabelMode())->normalize(null));
        self::assertNull((new InheritYesNo())->normalize(null));
        self::assertNull((new ButtonLabel())->normalize(null));
        self::assertSame(' ', (new ButtonLabel())->normalize('  '));
        self::assertSame('custom', (new LabelMode())->normalize('custom'));
        self::assertSame('inherit', (new LabelMode())->normalize('inherit'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validTriStateValues')]
    public function testNativeTriStateValuesAreCanonical(mixed $value, int $expected): void
    {
        self::assertSame($expected, (new InheritYesNo())->normalize($value));
    }

    public static function validTriStateValues(): array
    {
        return [[-1, -1], ['-1', -1], [0, 0], ['0', 0], [1, 1], ['1', 1]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAttributes')]
    public function testInvalidProductAttributesAreRejected(string $backend, mixed $value): void
    {
        $this->expectException(LocalizedException::class);
        (new $backend())->normalize($value);
    }

    public static function invalidAttributes(): array
    {
        return [[LabelMode::class, []], [LabelMode::class, 'hidden'], [LabelMode::class, 1],
            [InheritYesNo::class, []], [InheritYesNo::class, '2'], [InheritYesNo::class, '1 OR 1=1'],
            [InheritYesNo::class, new \stdClass()], [ButtonLabel::class, []],
            [ButtonLabel::class, new \stdClass()], [ButtonLabel::class, str_repeat('é', 121)],
            [ButtonLabel::class, "Printable\0hidden"], [ButtonLabel::class, "Invalid\xFFUTF8"]];
    }

    public function testUnicodeLengthIsCountedInCharacters(): void
    {
        self::assertSame(str_repeat('é', 120), (new ButtonLabel())->normalize(str_repeat('é', 120)));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nativeUseDefaultSentinels')]
    public function testNativeUseDefaultSentinelsAreNotRewrittenByBeforeSave(string $class, mixed $sentinel): void
    {
        $backend = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods(['getAttribute'])->getMock();
        $backend->method('getAttribute')->willReturn(new class {
            public function getAttributeCode(): string { return 'test_attribute'; }
        });
        $product = $this->createMock(DataObject::class);
        $product->method('hasData')->with('test_attribute')->willReturn(true);
        $product->method('getData')->with('test_attribute')->willReturn($sentinel);
        $product->expects(self::never())->method('setData');
        self::assertSame($backend, $backend->beforeSave($product));
    }

    public static function nativeUseDefaultSentinels(): array
    {
        return [[ButtonLabel::class, false], [ButtonLabel::class, null], [LabelMode::class, false],
            [LabelMode::class, null], [InheritYesNo::class, false], [InheritYesNo::class, null]];
    }

    public function testExplicitNoRemainsZeroInsteadOfBecomingUseDefault(): void
    {
        $backend = $this->getMockBuilder(InheritYesNo::class)->disableOriginalConstructor()->onlyMethods(['getAttribute'])->getMock();
        $backend->method('getAttribute')->willReturn(new class {
            public function getAttributeCode(): string { return 'test_attribute'; }
        });
        $product = $this->createMock(DataObject::class);
        $product->method('hasData')->willReturn(true);
        $product->method('getData')->willReturn('0');
        $product->expects(self::once())->method('setData')->with('test_attribute', 0);
        $backend->beforeSave($product);
    }
}
