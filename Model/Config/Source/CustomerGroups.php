<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Source;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;
class CustomerGroups implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }
    public function toOptionArray(): array
    {
        return $this->collectionFactory->create()->toOptionArray();
    }
}
