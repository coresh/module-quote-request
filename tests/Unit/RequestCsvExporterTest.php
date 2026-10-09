<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Ui\Export\CsvCellSanitizer;
use Coresh\QuoteRequest\Ui\Export\RequestCsvExporter;
use Coresh\QuoteRequest\Ui\Service\SelectedRequests;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface as DirectoryWriter;
use Magento\Framework\Filesystem\File\WriteInterface as FileWriter;
use PHPUnit\Framework\TestCase;

class RequestCsvExporterTest extends TestCase
{
    public function testExportStreamsOnlyDeclaredColumnsAndSanitizesEveryCell(): void
    {
        $written = [];
        $stream = $this->createMock(FileWriter::class);
        $stream->expects(self::once())->method('lock');
        $stream->expects(self::once())->method('unlock');
        $stream->expects(self::once())->method('close');
        $stream->method('writeCsv')->willReturnCallback(static function (array $row) use (&$written) {
            $written[] = $row;
            return 1;
        });
        $directory = $this->createMock(DirectoryWriter::class);
        $directory->expects(self::once())->method('create')->with('export');
        $directory->expects(self::once())->method('openFile')->with(
            self::matchesRegularExpression('#^export/coresh_quote_requests_[0-9a-f]{32}\\.csv$#'), 'w+'
        )->willReturn($stream);
        $directory->expects(self::never())->method('delete');
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->with('var')->willReturn($directory);
        $selection = $this->createMock(SelectedRequests::class);
        $selection->method('rows')->willReturnCallback(static function (): \Generator {
            yield ['request_id' => 2, 'customer_name' => '=HYPERLINK("https://invalid")',
                'customer_email' => 'user@example.test', 'customer_telephone' => '+1234',
                'message' => "  @SUM(1)", 'product_name' => 'Product', 'quantity' => '2.0000',
                'unexpected_secret' => 'MUST_NOT_EXPORT'];
        });
        $result = (new RequestCsvExporter($filesystem, $selection, new CsvCellSanitizer()))->getCsvFile();
        self::assertSame('filename', $result['type']);
        self::assertTrue($result['rm']);
        self::assertCount(2, $written);
        self::assertCount(20, $written[0]);
        self::assertCount(20, $written[1]);
        self::assertSame('Request ID', $written[0][0]);
        self::assertSame('2', $written[1][0]);
        self::assertSame("'=HYPERLINK(\"https://invalid\")", $written[1][1]);
        self::assertSame("'+1234", $written[1][3]);
        self::assertSame("'  @SUM(1)", $written[1][4]);
        self::assertSame('2.0000', $written[1][8]);
        self::assertNotContains('MUST_NOT_EXPORT', $written[1]);
    }

    public function testSelectionFailureClosesAndDeletesPartialExport(): void
    {
        $stream = $this->createMock(FileWriter::class);
        $stream->expects(self::once())->method('lock');
        $stream->expects(self::once())->method('unlock');
        $stream->expects(self::once())->method('close');
        $directory = $this->createMock(DirectoryWriter::class);
        $directory->method('openFile')->willReturn($stream);
        $directory->expects(self::once())->method('delete')->with(
            self::matchesRegularExpression('#^export/coresh_quote_requests_[0-9a-f]{32}\\.csv$#')
        );
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);
        $selection = $this->createMock(SelectedRequests::class);
        $selection->method('rows')->willReturnCallback(static function (): \Generator {
            throw new \RuntimeException('selection_failed');
            yield [];
        });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('selection_failed');
        (new RequestCsvExporter($filesystem, $selection, new CsvCellSanitizer()))->getCsvFile();
    }
}
