<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Attribute\Backend;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\Exception\LocalizedException;
class InheritYesNo extends AbstractBackend
{
    public function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (!is_scalar($value) || !in_array(trim((string) $value), ['-1', '0', '1'], true)) {
            throw new LocalizedException(__('Quote Request product options must be Use store configuration, No or Yes.'));
        }
        return (int) $value;
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
