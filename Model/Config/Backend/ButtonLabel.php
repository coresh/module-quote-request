<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Backend;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
class ButtonLabel extends Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        if (!is_scalar($value) || !mb_check_encoding((string) $value, 'UTF-8') || mb_strlen(trim((string) $value)) > 120 || preg_match('/[\x00-\x1f\x7f]/', (string) $value)) {
            throw new LocalizedException(__('Button text must contain up to 120 printable characters.'));
        }
        $this->setValue(trim((string) $value));
        return parent::beforeSave();
    }
}
