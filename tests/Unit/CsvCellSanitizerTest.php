<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Ui\Export\CsvCellSanitizer;
use PHPUnit\Framework\TestCase;

class CsvCellSanitizerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('formulaPayloads')]
    public function testSpreadsheetFormulasAreForcedToText(string $payload): void
    {
        self::assertSame("'" . $payload, (new CsvCellSanitizer())->sanitize($payload));
    }

    public static function formulaPayloads(): array
    {
        return [['=SUM(1,2)'], ['+cmd|payload'], ['-SUM(1,2)'], ['@SUM(1,2)'], ['  =SUM(1,2)'],
            ["\t=SUM(1,2)"], ["\r\n@SUM(1,2)"], ["\u{00A0}=SUM(1,2)"],
            ["\u{200B}=SUM(1,2)"], ["\u{FEFF}@SUM(1,2)"], ["\0=SUM(1,2)"],
            ["\x01plain text"], ["\x7Fplain text"], ["\xFF=SUM(1,2)"]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('plainValues')]
    public function testOrdinaryValuesAndAlreadyQuotedTextArePreserved(mixed $payload, string $expected): void
    {
        self::assertSame($expected, (new CsvCellSanitizer())->sanitize($payload));
    }

    public static function plainValues(): array
    {
        return [[null, ''], ['', ''], [42, '42'], ['3.0000', '3.0000'], ['John', 'John'],
            ["'=SUM(1,2)", "'=SUM(1,2)"], ['Text =SUM(1,2)', 'Text =SUM(1,2)'],
            ['Message with "quotes", commas', 'Message with "quotes", commas']];
    }
}
