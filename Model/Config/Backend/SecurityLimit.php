<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Backend;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
class SecurityLimit extends Value
{
    public function beforeSave()
    {
        $value = $this->getValue();
        $path = (string) $this->getPath();
        $isQuantity = str_ends_with($path, '/max_quantity');
        $limits = [
            'rate_limit_max' => [1, 1000],
            'rate_limit_window' => [1, 86400],
            'max_message_length' => [1, 20000]
        ];
        if (!is_scalar($value) || !preg_match($isQuantity ? '/\A[0-9]+(?:\.[0-9]{1,4})?\z/' : '/\A[0-9]+\z/', (string) $value)) {
            throw new LocalizedException(__('Enter a positive numeric limit. Quantity permits up to four decimal places.'));
        }
        $number = (float) $value;
        $field = basename($path);
        [$minimum, $maximum] = $isQuantity ? [0.0001, 99999999.9999] : ($limits[$field] ?? [1, 1000000]);
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            throw new LocalizedException(__('The configured limit is outside the allowed range (%1 to %2).', $minimum, $maximum));
        }
        return parent::beforeSave();
    }
}
