<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Source;
use Magento\Framework\Data\OptionSourceInterface;
class Scope implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'global', 'label' => __('Global — all products')],
            ['value' => 'product', 'label' => __('Products and enabled category rules')],
            ['value' => 'category', 'label' => __('Selected categories and enabled product/category rules')],
        ];
    }
}
