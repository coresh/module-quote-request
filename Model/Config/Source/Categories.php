<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Model\Config\Source;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;
class Categories implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }
    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'path']);
        $collection->addFieldToFilter('entity_id', ['neq' => 1]);
        $collection->addAttributeToSort('path', 'ASC');
        $options = [];
        foreach ($collection as $category) {
            $options[] = [
                'value' => (string) $category->getId(),
                'label' => (string) $category->getName() . ' [ID ' . (int) $category->getId() . '; ' . (string) $category->getPath() . ']'
            ];
        }
        return $options;
    }
}
