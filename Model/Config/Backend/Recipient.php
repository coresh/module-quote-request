<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Backend;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
class Recipient extends Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        if (!is_scalar($value) && $value !== null) {
            throw new LocalizedException(__('Enter a single valid recipient email address.'));
        }
        $value = trim((string) $value);
        if ($value !== '' && (strlen($value) > 254 || preg_match('/[\r\n]/', $value)
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            throw new LocalizedException(__('Enter a single valid recipient email address.'));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
