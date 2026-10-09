<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Export;

use Coresh\QuoteRequest\Ui\Service\SelectedRequests;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class RequestCsvExporter
{
    private const COLUMNS = [
        'request_id' => 'Request ID', 'customer_name' => 'Name', 'customer_email' => 'Email',
        'customer_telephone' => 'Phone', 'message' => 'Description', 'product_name' => 'Product Name',
        'product_sku' => 'SKU', 'product_id' => 'Product ID', 'quantity' => 'Quantity',
        'quantity_origin' => 'Quantity Origin', 'product_url' => 'Product URL',
        'selected_product_id' => 'Selected Product ID', 'selected_product_sku' => 'Selected SKU',
        'selected_options' => 'Selected Options', 'store_id' => 'Store ID',
        'request_status' => 'Request Status', 'email_status' => 'Email Status',
        'email_attempts' => 'Email Attempts', 'created_at' => 'Inquiry Date (UTC)',
        'last_email_sent_at' => 'Last Email Sent (UTC)'
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly SelectedRequests $selection,
        private readonly CsvCellSanitizer $sanitizer
    ) {
    }

    public function getCsvFile(): array
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('export');
        $file = 'export/coresh_quote_requests_' . bin2hex(random_bytes(16)) . '.csv';
        $stream = $directory->openFile($file, 'w+');
        $locked = false;
        try {
            $stream->lock();
            $locked = true;
            $stream->writeCsv(array_values(self::COLUMNS));
            foreach ($this->selection->rows() as $row) {
                $values = [];
                foreach (self::COLUMNS as $field => $label) {
                    $values[] = $this->sanitizer->sanitize($row[$field] ?? null);
                }
                $stream->writeCsv($values);
            }
        } catch (\Throwable $exception) {
            if ($locked) {
                $stream->unlock();
            }
            $stream->close();
            $directory->delete($file);
            throw $exception;
        }
        $stream->unlock();
        $stream->close();
        return ['type' => 'filename', 'value' => $file, 'rm' => true];
    }
}
