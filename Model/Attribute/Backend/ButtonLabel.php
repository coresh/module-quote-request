<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Attribute\Backend;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\Exception\LocalizedException;
class ButtonLabel extends AbstractBackend
{
    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new LocalizedException(__('Quote Request button text must be plain text.'));
        }
        $value = trim((string) $value);
        if (!mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 120 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            throw new LocalizedException(__('Quote Request button text must contain up to 120 printable characters.'));
        }
        // Some EAV persistence paths delete blank varchar store values and then inherit a default label.
        // Use a nonempty sentinel consistently across paths; ProductDisplayPolicy trims it to hidden enquiry.
        return $value === '' ? ' ' : $value;
    }
    public function beforeSave($object)
    {
        $code = $this->getAttribute()->getAttributeCode();
        if ($object->hasData($code)) {
            $value = $object->getData($code);
            // Magento product Use Default may use false as the EAV store-value deletion sentinel.
            // Preserve both false and null instead of turning inheritance into an explicit product value.
            if ($value === false || $value === null) {
                return $this;
            }
            $object->setData($code, $this->normalize($value));
        }
        return parent::beforeSave($object);
    }
}
