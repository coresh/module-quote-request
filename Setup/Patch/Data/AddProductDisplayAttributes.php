<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/** A new patch so installations with the v1 patch already recorded receive all new attributes. */
class AddProductDisplayAttributes implements DataPatchInterface
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
            $common = [
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'visible' => true,
                'user_defined' => true,
                'group' => 'Quote Request',
                'used_in_product_listing' => true,
                'visible_on_front' => false,
                'is_html_allowed_on_front' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'unique' => false
            ];
            $attributes = [
                'coresh_quote_request_label_mode' => [
                    'type' => 'varchar', 'label' => 'Inquiry Label Mode', 'input' => 'select',
                    'default' => 'inherit', 'sort_order' => 110,
                    'source' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Source\\LabelMode',
                    'backend' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Backend\\LabelMode',
                    'note' => 'Use store text preserves existing behaviour. Custom mode with an empty label hides enquiry controls and blocks public enquiry submission.'
                ],
                'coresh_quote_request_button_label' => [
                    'type' => 'varchar', 'label' => 'Label For Inquiry Button', 'input' => 'text',
                    'default' => null, 'sort_order' => 120,
                    'backend' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Backend\\ButtonLabel',
                    'note' => 'Applies when Enable Quote Request is Yes and Inquiry Label Mode is Custom. Empty custom text hides inquiry. Maximum 120 characters; no HTML rendering.'
                ],
                'coresh_quote_request_allow_cart' => [
                    'type' => 'int', 'label' => 'Allow Add to Cart', 'input' => 'select',
                    'default' => -1, 'sort_order' => 130,
                    'source' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Source\\InheritYesNo',
                    'backend' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Backend\\InheritYesNo',
                    'note' => 'Applies to this enabled product storefront CTA only. Yes never overrides Disable online purchasing. No does not block direct API purchases.'
                ],
                'coresh_quote_request_show_price' => [
                    'type' => 'int', 'label' => 'Disclose Price', 'input' => 'select',
                    'default' => -1, 'sort_order' => 140,
                    'source' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Source\\InheritYesNo',
                    'backend' => 'Coresh\\QuoteRequest\\Model\\Attribute\\Backend\\InheritYesNo',
                    'note' => 'Applies to eligible enabled product storefront HTML. Yes shows price, No hides it, Use store configuration inherits Hide prices. This does not hide API or third-party structured data.'
                ]
            ];
            foreach ($attributes as $code => $definition) {
                if (!$setup->getAttributeId(Product::ENTITY, $code)) {
                    $setup->addAttribute(Product::ENTITY, $code, array_merge($common, $definition));
                }
            }
            $entityTypeId = (int) $setup->getEntityTypeId(Product::ENTITY);
            foreach ($setup->getAllAttributeSetIds($entityTypeId) as $setId) {
                $setup->addAttributeGroup($entityTypeId, (int) $setId, 'Quote Request', 90);
                foreach ($attributes as $code => $definition) {
                    $setup->addAttributeToSet($entityTypeId, (int) $setId, 'Quote Request', $code, $definition['sort_order']);
                }
            }
        } finally {
            $connection->endSetup();
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddQuoteRequestAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
