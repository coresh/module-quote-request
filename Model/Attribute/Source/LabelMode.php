<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Attribute\Source;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
class LabelMode extends AbstractSource
{
    public function getAllOptions()
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => 'inherit', 'label' => __('Use store button / link text')],
                ['value' => 'custom', 'label' => __('Custom text; empty text hides enquiry')]
            ];
        }
        return $this->_options;
    }
}
