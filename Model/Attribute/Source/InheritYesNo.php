<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Attribute\Source;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
class InheritYesNo extends AbstractSource
{
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => -1, 'label' => __('Use store configuration')],
                ['value' => 0, 'label' => __('No')],
                ['value' => 1, 'label' => __('Yes')]
            ];
        }
        return $this->_options;
    }
}
