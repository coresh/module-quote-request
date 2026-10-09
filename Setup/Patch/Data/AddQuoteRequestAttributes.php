<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** Non-destructive; fresh install and repeated apply both preserve existing EAV values. */
class AddQuoteRequestAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();
        try {
            $setup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
            foreach ([Product::ENTITY, Category::ENTITY] as $entityType) {
                if (!$setup->getAttributeId($entityType, 'coresh_quote_request_enabled')) {
                    $setup->addAttribute($entityType, 'coresh_quote_request_enabled', [
                        'type' => 'int',
                        'label' => 'Enable Quote Request',
                        'input' => 'boolean',
                        'source' => 'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Boolean',
                        'required' => false,
                        'default' => '0',
                        'global' => ScopedAttributeInterface::SCOPE_STORE,
                        'visible' => true,
                        'user_defined' => true,
                        'group' => 'Quote Request',
                        'sort_order' => 100,
                        'used_in_product_listing' => $entityType === Product::ENTITY,
                        'visible_on_front' => false,
                        'is_html_allowed_on_front' => false,
                        'searchable' => false,
                        'filterable' => false,
                        'comparable' => false,
                        'unique' => false
                    ]);
                }
                $entityTypeId = (int) $setup->getEntityTypeId($entityType);
                foreach ($setup->getAllAttributeSetIds($entityTypeId) as $setId) {
                    $setup->addAttributeGroup($entityTypeId, (int) $setId, 'Quote Request', 90);
                    $setup->addAttributeToSet($entityTypeId, (int) $setId, 'Quote Request', 'coresh_quote_request_enabled', 100);
                }
            }
        } finally {
            $connection->endSetup();
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
