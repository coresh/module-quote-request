<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Attribute\Backend;
use Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend;
use Magento\Framework\Exception\LocalizedException;
class LabelMode extends AbstractBackend
{
    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null; // Native Use Default must remain a nullable store-value deletion.
        }
        if (!is_string($value) || !in_array($value, ['inherit', 'custom'], true)) {
            throw new LocalizedException(__('Quote Request label mode must be inherit or custom.'));
        }
        return $value;
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
