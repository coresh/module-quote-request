<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Integration;

use Coresh\QuoteRequest\Model\Catalog\ProductDisplayPolicy;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper\AttributeFilter;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Group;
use Magento\Store\Model\ResourceModel\Group as GroupResource;
use Magento\Store\Model\ResourceModel\Store as StoreResource;
use Magento\Store\Model\ResourceModel\Website as WebsiteResource;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * NOT RUN in the standalone module build: requires an installed Magento test database.
 *
 * Uses native EAV repositories and the exact AttributeFilter invoked by the admin
 * Product Initialization Helper for use_default[attribute_code] = "1". This tests
 * that native helper branch and persistence, not the full admin controller or UI.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 * @magentoAppArea adminhtml
 */
class ProductDisplayScopeTest extends TestCase
{
    private const LABEL = 'coresh_quote_request_button_label';
    private const MODE = 'coresh_quote_request_label_mode';
    private const CART = 'coresh_quote_request_allow_cart';
    private const PRICE = 'coresh_quote_request_show_price';

    private ProductRepositoryInterface $products;
    private StoreManagerInterface $stores;
    private ResourceConnection $db;
    private ProductDisplayPolicy $policy;
    private ?Website $website = null;
    private ?Group $group = null;
    private ?Store $store = null;
    private ?int $productId = null;
    private int $originalStoreId;
    private string $sku;

    protected function setUp(): void
    {
        $om = Bootstrap::getObjectManager();
        $this->products = $om->get(ProductRepositoryInterface::class);
        $this->stores = $om->get(StoreManagerInterface::class);
        $this->db = $om->get(ResourceConnection::class);
        $this->policy = $om->get(ProductDisplayPolicy::class);
        $this->originalStoreId = (int) $this->stores->getStore()->getId();
        $defaultStore = $this->stores->getDefaultStoreView();
        self::assertNotNull($defaultStore, 'Magento integration fixtures require a default store view.');
        $defaultGroup = $this->stores->getGroup((int) $defaultStore->getStoreGroupId());
        $suffix = bin2hex(random_bytes(6));
        $this->sku = 'CORESH-SCOPE-' . $suffix;

        $this->website = $om->create(Website::class);
        $this->website->setCode('coresh_scope_' . $suffix)->setName('Coresh scope fixture')->setSortOrder(50);
        $om->get(WebsiteResource::class)->save($this->website);
        $this->group = $om->create(Group::class);
        $this->group->setWebsiteId((int) $this->website->getId())->setCode('coresh_group_' . $suffix)
            ->setName('Coresh scope fixture')->setRootCategoryId((int) $defaultGroup->getRootCategoryId());
        $om->get(GroupResource::class)->save($this->group);
        $this->store = $om->create(Store::class);
        $this->store->setCode('coresh_view_' . $suffix)->setName('Coresh scope fixture')
            ->setWebsiteId((int) $this->website->getId())->setGroupId((int) $this->group->getId())
            ->setIsActive(1)->setSortOrder(50);
        $om->get(StoreResource::class)->save($this->store);
        $this->group->setDefaultStoreId((int) $this->store->getId());
        $om->get(GroupResource::class)->save($this->group);
        $this->website->setDefaultGroupId((int) $this->group->getId());
        $om->get(WebsiteResource::class)->save($this->website);
        $this->stores->reinitStores();

        $this->stores->setCurrentStore(Store::DEFAULT_STORE_ID);
        $product = $om->create(Product::class);
        $product->setStoreId(Store::DEFAULT_STORE_ID)->setTypeId('simple')->setSku($this->sku)
            ->setName('Scoped product display fixture')->setPrice(10)->setWeight(1)
            ->setStatus(Status::STATUS_ENABLED)->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setAttributeSetId((int) $om->get(EavConfig::class)->getEntityType(Product::ENTITY)
                ->getDefaultAttributeSetId())
            ->setWebsiteIds([(int) $this->website->getId()])->setStockData(['qty' => 10, 'is_in_stock' => 1]);
        $product->addData(['coresh_quote_request_enabled' => 1, self::MODE => 'custom',
            self::LABEL => 'Parent product label', self::CART => 1, self::PRICE => 1]);
        $this->productId = (int) $this->products->save($product)->getId();
    }

    protected function tearDown(): void
    {
        $om = Bootstrap::getObjectManager();
        try {
            $this->stores->setCurrentStore(Store::DEFAULT_STORE_ID);
            if ($this->productId !== null) {
                try {
                    $this->products->deleteById($this->sku);
                } catch (NoSuchEntityException $exception) {
                    // A failed fixture or prior cleanup may already have removed the entity.
                }
            }
            if ($this->website !== null && $this->website->getId()) {
                $this->website->setDefaultGroupId(0);
                $om->get(WebsiteResource::class)->save($this->website);
            }
            if ($this->group !== null && $this->group->getId()) {
                $this->group->setDefaultStoreId(0);
                $om->get(GroupResource::class)->save($this->group);
            }
            if ($this->store !== null && $this->store->getId()) {
                $om->get(StoreResource::class)->delete($this->store);
            }
            if ($this->group !== null && $this->group->getId()) {
                $om->get(GroupResource::class)->delete($this->group);
            }
            if ($this->website !== null && $this->website->getId()) {
                $om->get(WebsiteResource::class)->delete($this->website);
            }
        } finally {
            $this->stores->reinitStores();
            $this->stores->setCurrentStore($this->originalStoreId);
        }
    }

    public function testExplicitBlankCustomStoreLabelPersistsAsHiddenWithoutChangingParent(): void
    {
        $storeId = (int) $this->store->getId();
        $child = $this->saveValues($storeId, [self::MODE => 'custom', self::LABEL => '']);
        self::assertSame('', $this->policy->getButtonLabel($child, $storeId));
        self::assertFalse($this->policy->isInquiryAllowed($child, $storeId));
        $row = $this->attributeRow($child, self::LABEL, $storeId);
        self::assertIsArray($row, 'An explicit blank label must retain a store override.');
        self::assertSame(' ', $row['value'], 'The backend persists its documented nonempty blank sentinel.');
        $parent = $this->loadProduct(Store::DEFAULT_STORE_ID);
        self::assertSame('Parent product label', $parent->getData(self::LABEL));
        self::assertSame('Parent product label', $this->policy->getButtonLabel($parent, $storeId));
    }

    public function testTriStateZeroIsPersistedRatherThanTreatedAsUseDefault(): void
    {
        $storeId = (int) $this->store->getId();
        $child = $this->saveValues($storeId, [self::CART => 0, self::PRICE => '0']);
        foreach ([self::CART, self::PRICE] as $code) {
            self::assertSame(0, (int) $child->getData($code));
            $row = $this->attributeRow($child, $code, $storeId);
            self::assertIsArray($row, 'No is an explicit store value, not a deletion sentinel.');
            self::assertSame('0', (string) $row['value']);
            self::assertSame(1, (int) $this->loadProduct(Store::DEFAULT_STORE_ID)->getData($code));
        }
    }

    public function testNativeAdminUseDefaultRemovesVarcharFalseAndIntegerNullOverrides(): void
    {
        $storeId = (int) $this->store->getId();
        $this->saveValues(Store::DEFAULT_STORE_ID, [self::MODE => 'inherit']);
        $child = $this->saveValues($storeId, [self::MODE => 'custom', self::LABEL => '',
            self::CART => 0, self::PRICE => 0]);
        $codes = [self::LABEL, self::MODE, self::CART, self::PRICE];
        foreach ($codes as $code) {
            self::assertIsArray($this->attributeRow($child, $code, $storeId));
        }
        $data = [];
        foreach ($codes as $code) {
            $data[$code] = $child->getData($code);
        }
        $native = Bootstrap::getObjectManager()->get(AttributeFilter::class)->prepareProductAttributes(
            $child,
            $data,
            array_fill_keys($codes, '1')
        );
        // These are the actual native helper's distinct sentinels; do not substitute normalize(false).
        self::assertFalse($native[self::LABEL]);
        self::assertFalse($native[self::MODE]);
        self::assertNull($native[self::CART]);
        self::assertNull($native[self::PRICE]);
        $child->addData($native);
        $this->stores->setCurrentStore($storeId);
        $this->products->save($child);
        $reloaded = $this->loadProduct($storeId);
        foreach ($codes as $code) {
            self::assertFalse($this->attributeRow($reloaded, $code, $storeId),
                'Native Use Default must delete the store row for ' . $code);
        }
        self::assertSame('Parent product label', $reloaded->getData(self::LABEL));
        self::assertSame('inherit', $reloaded->getData(self::MODE));
        self::assertSame(1, (int) $reloaded->getData(self::CART));
        self::assertSame(1, (int) $reloaded->getData(self::PRICE));
    }

    private function saveValues(int $storeId, array $values): Product
    {
        $product = $this->loadProduct($storeId);
        $product->addData($values);
        $this->stores->setCurrentStore($storeId);
        $this->products->save($product);
        return $this->loadProduct($storeId);
    }

    private function loadProduct(int $storeId): Product
    {
        $product = $this->products->getById($this->productId, false, $storeId, true);
        self::assertInstanceOf(Product::class, $product);
        return $product;
    }

    /** Raw store rows distinguish absence/inheritance from a blank or explicit zero. */
    private function attributeRow(Product $product, string $code, int $storeId): array|false
    {
        $attribute = $product->getResource()->getAttribute($code);
        $linkField = $product->getResource()->getLinkField();
        return $this->db->getConnection()->fetchRow($this->db->getConnection()->select()
            ->from($attribute->getBackendTable(), ['value'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->where('store_id = ?', $storeId)
            ->where($linkField . ' = ?', (int) $product->getData($linkField)));
    }
}
