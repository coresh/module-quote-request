<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Export;

/** CSV quotes alone do not prevent spreadsheet formulas. Preserve values as text. */
class CsvCellSanitizer
{
    public function sanitize(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;
        $formula = preg_match('/\A[\p{Z}\p{Cf}\x00-\x20]*[=+\-@]/u', $text);
        if ($formula === 1 || $formula === false || preg_match('/\A[\x00-\x1F\x7F]/', $text) === 1) {
            return "'" . $text;
        }
        return $text;
    }
}
