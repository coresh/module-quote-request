<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class RequestDisplay implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'button', 'label' => __('Button')],
            ['value' => 'link', 'label' => __('Text link')],
            ['value' => 'label', 'label' => __('Static label (no enquiry form)')],
        ];
    }
}
