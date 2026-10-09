<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Backend;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
class Date extends Value
{
    /** Normalize the documented date format before invoking PHP's parser. */
    public function normalize(mixed $value): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new LocalizedException(__('Enter a date using YYYY-MM-DD.'));
        }
        $value = trim((string) $value);
        if ($value !== '' && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) !== 1) {
            throw new LocalizedException(__('Enter a valid date using YYYY-MM-DD.'));
        }
        $date = $value === '' ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($value !== '' && ($date === false || $date->format('Y-m-d') !== $value)) {
            throw new LocalizedException(__('Enter a valid date using YYYY-MM-DD.'));
        }
        return $value;
    }

    public function beforeSave()
    {
        $value = $this->normalize($this->getValue());
        $from = (string) $this->getFieldsetDataValue('from_date');
        $to = (string) $this->getFieldsetDataValue('to_date');
        if ($from !== '' && $to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from . '')
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to . '') && $from > $to) {
            throw new LocalizedException(__('The end date must be on or after the start date.'));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
