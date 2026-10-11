<?php
/**
 * MINIMAL TEST DOUBLES. These are not Magento classes or a Magento emulator.
 * Used only to exercise this module's pure policies in an environment without Magento.
 * A real Magento unit run loads vendor/autoload.php instead (MAGENTO_UNIT_AUTOLOAD).
 */
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface {
        public function getValue($path = null, $scopeType = 'default', $scopeCode = null);
        public function isSetFlag($path, $scopeType = 'default', $scopeCode = null);
    }
}
namespace Magento\Store\Model {
    interface ScopeInterface { public const SCOPE_STORE = 'store'; }
    interface StoreManagerInterface { public function getStore($storeId = null); }
    class Store { public function __construct(private int $id = 1) {} public function getId(): int { return $this->id; } public function getName(): string { return 'Test store'; } public function isActive() { return true; } public function getWebsiteId() { return 1; } }
}
namespace Magento\Framework {
    class Phrase {
        public function __construct(private string $text, private array $args = []) {}
        public function __toString(): string { return $this->text; }
    }
    class DataObject {
        protected array $_data = [];
        public function __construct(array $data = []) { $this->_data = $data; }
        public function getData($key = '', $index = null) { return $key === '' ? $this->_data : ($this->_data[$key] ?? null); }
        public function setData($key, $value = null) { if (is_array($key)) { $this->_data = $key; } else { $this->_data[$key] = $value; } return $this; }
        public function getId() { return $this->_data['request_id'] ?? null; }
        public function hasData($key = '') { return $key !== '' ? array_key_exists($key, $this->_data) : $this->_data !== []; }
        public function __call(string $method, array $args) { $key = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 3))); return str_starts_with($method, 'get') ? $this->getData($key) : $this->setData($key, $args[0] ?? null); }
    }
}
namespace Magento\Framework\Data {
    class Collection {}
}
namespace Magento\Framework\Data\Collection {
    abstract class AbstractDb extends \Magento\Framework\Data\Collection { public function addFieldToFilter($field, $condition = null) { return $this; } }
}
namespace Magento\Framework\View\Element\UiComponent\DataProvider {
    class DataProvider { public function getName() { return ''; } public function getData() { return []; } }
    interface FilterApplierInterface { public function apply(\Magento\Framework\Data\Collection $collection, \Magento\Framework\Api\Filter $filter); }
}
namespace Magento\Framework\Exception {
    class LocalizedException extends \Exception {
        public function __construct(\Magento\Framework\Phrase $phrase) { parent::__construct((string) $phrase); }
    }
}
namespace Magento\Framework\Stdlib\DateTime {
    interface TimezoneInterface { public function getConfigTimezone($scopeType = null, $scopeCode = null); }
}
namespace Magento\Catalog\Api\Data {
    interface ProductInterface { public function getCustomAttribute($attributeCode); public function getExtensionAttributes(); }
}
namespace Magento\Framework\Api {
    interface AttributeInterface { public function getValue(); }
    class Filter { public function getValue() { return null; } }
}
namespace Magento\Customer\Model {
    class Context { public const CONTEXT_GROUP = 'customer_group'; }
    class Group { public const NOT_LOGGED_IN_ID = 0; }
    class Session extends \Magento\Framework\DataObject { public function isLoggedIn() { return false; } public function getCustomerId() { return null; } public function getCustomerGroupId() { return 0; } }
}
namespace Magento\Framework\App\Http {
    class Context { public function getValue($name) { return null; } }
}
namespace Magento\Framework\Filesystem\Directory {
    interface WriteInterface { public function create($path); public function openFile($path, $mode = 'r'); public function delete($path = null); }
}
namespace Magento\Framework\Filesystem\File {
    interface WriteInterface { public function lock($lockMode = LOCK_EX); public function unlock(); public function writeCsv(array $data, $delimiter = ',', $enclosure = '"'); public function close(); }
}
namespace Magento\Framework\App\Filesystem {
    class DirectoryList { public const VAR_DIR = 'var'; }
}
namespace Magento\Framework {
    class Filesystem { public function getDirectoryWrite($code, $driverCode = 'file') { return null; } }
}
namespace Magento\Framework\App {
    interface RequestInterface { public function getParam($key, $default = null); }
    class Area { public const AREA_FRONTEND = 'frontend'; public const AREA_ADMINHTML = 'adminhtml'; public const AREA_CRONTAB = 'crontab'; }
    class State { public function getAreaCode() { return 'frontend'; } }
    class ResourceConnection { public function getConnection($resourceName = 'default') { return null; } public function getTableName($modelEntity, $connectionName = null) { return $modelEntity; } }
}
namespace Magento\Catalog\Model\ResourceModel\Product {
    class Collection { public function getItems() { return []; } public function isLoaded() { return false; } public function addAttributeToSelect($attribute, $joinType = false) { return $this; } }
}
namespace Magento\Catalog\Model {
    class Product extends \Magento\Framework\DataObject implements \Magento\Catalog\Api\Data\ProductInterface {
        public function getId() { return $this->_data['entity_id'] ?? null; }
        public function getCustomAttribute($attributeCode) { return null; }
        public function getExtensionAttributes() { return null; }
        public function getWebsiteIds() { return []; }
        public function getStatus() { return 1; }
        public function getVisibility() { return 4; }
        public function getTypeId() { return 'simple'; }
        public function getName() { return ''; }
        public function getSku() { return ''; }
        public function getProductUrl($useSid = null) { return ''; }
        public function getAttributeText($attributeCode) { return ''; }
    }
}
namespace Magento\Catalog\Api {
    interface ProductRepositoryInterface { public function getById($productId, $editMode = false, $storeId = null, $forceReload = false); }
}
namespace Magento\Catalog\Model\Product\Attribute\Source {
    class Status { public const STATUS_ENABLED = 1; }
}
namespace Magento\Catalog\Model\Product {
    class Visibility { public const VISIBILITY_IN_CATALOG = 2; public const VISIBILITY_IN_SEARCH = 3; public const VISIBILITY_BOTH = 4; }
}
namespace Magento\ConfigurableProduct\Model\Product\Type {
    class Configurable { public function getConfigurableAttributes($product) { return []; } public function getUsedProducts($product, $requiredAttributeIds = null) { return []; } }
}
namespace Magento\CatalogInventory\Api {
    interface StockRegistryInterface { public function getStockItem($productId, $scopeId = null); }
}
namespace Magento\CatalogInventory\Api\Data {
    interface StockItemInterface { public function getIsQtyDecimal(); }
}
namespace Magento\Framework\DB\Adapter {
    interface AdapterInterface { public function select(); public function fetchAll($sql, $bind = []); public function fetchOne($sql, $bind = []); public function update($table, array $bind, $where = ''); public function startSetup(); public function endSetup(); public function insertOnDuplicate($table, array $data, array $fields = []); public function isTableExists($tableName); public function describeTable($tableName, $schemaName = null); public function beginTransaction(); public function commit(); public function rollBack(); public function query($sql, $bind = []); public function fetchRow($sql, $bind = []); public function delete($table, $where = ''); public function quoteIdentifier($ident, $auto = false); }
}
namespace Magento\Framework\Model\ResourceModel\Db {
    abstract class AbstractDb { protected function _init($mainTable, $idFieldName) {} public function getConnection() { return null; } public function getMainTable() { return ''; } public function save(\Magento\Framework\Model\AbstractModel $object) { return $this; } public function load(\Magento\Framework\Model\AbstractModel $object, $value, $field = null) { return $this; } }
}
namespace Magento\Framework\DB {
    class Select { public function from($name, $cols = '*', $schema = null) { return $this; } public function where($cond, $value = null, $type = null) { return $this; } public function limit($count = null, $offset = null) { return $this; } }
}
namespace Magento\Eav\Model\Entity\Attribute\Backend {
    abstract class AbstractBackend { public function beforeSave($object) { return $this; } public function getAttribute() { return null; } }
}
namespace Magento\Framework\Setup {
    interface ModuleDataSetupInterface { public function getConnection(); public function getTable($name); }
}
namespace Magento\Framework\Setup\Patch {
    interface DataPatchInterface { public function apply(); public static function getDependencies(); public function getAliases(); }
}
namespace Magento\Framework\Module {
    interface ModuleListInterface { public function getOne($name); }
}
namespace Magento\Quote\Api\Data {
    interface CartInterface { public function getId(); public function getStoreId(); }
}
namespace Magento\Quote\Api {
    interface CartRepositoryInterface { public function save(\Magento\Quote\Api\Data\CartInterface $quote); }
}
namespace Magento\Framework\App\Action {
    interface HttpPostActionInterface { public function execute(); }
}
namespace Magento\Framework\App\Request {
    class Http { public function isPost() { return false; } public function getPostValue($key = null) { return null; } public function getPost($key = null, $default = null) { return $default; } public function getParam($key, $default = null) { return $default; } public function getServer($key = null, $default = null) { return $default; } }
}
namespace Magento\Framework\Controller\Result {
    class Json { public function setHeader($name, $value, $replace = false) { return $this; } public function setHttpResponseCode($code) { return $this; } public function setData($data) { return $this; } }
    class JsonFactory { public function create(array $data = []) { return new Json(); } }
}
namespace Magento\Framework\Data\Form {
    class FormKey { public function getFormKey() { return ''; } }
}
namespace Magento\Framework\Data\Form\FormKey {
    class Validator { public function validate($request) { return false; } }
}
namespace Psr\Log {
    interface LoggerInterface { public function error($message, array $context = []); public function warning($message, array $context = []); }
}
namespace Magento\Framework\Model {
    abstract class AbstractModel extends \Magento\Framework\DataObject { protected function _init($resource): void {} }
}
namespace {
    if (!function_exists('__')) {
        function __(string $text, ...$arguments): \Magento\Framework\Phrase { return new \Magento\Framework\Phrase($text, $arguments); }
    }
}

namespace Magento\Framework\DB\Sql {
    class Expression { public function __construct(private string $expression) {} public function __toString(): string { return $this->expression; } }
}
namespace Magento\Framework\Translate\Inline {
    interface StateInterface {
        public function disable();
        public function enable();
        public function isEnabled();
        public function suspend($status = false);
        public function resume($status = true);
    }
}
namespace Magento\Framework\Mail {
    interface TransportInterface { public function sendMessage(); }
}
namespace Magento\Framework\Mail\Template {
    class TransportBuilder {
        public function setTemplateIdentifier($identifier) { return $this; }
        public function setTemplateOptions($options) { return $this; }
        public function setTemplateVars($vars) { return $this; }
        public function setFromByScope($from, $scopeId = null) { return $this; }
        public function addTo($address, $name = '') { return $this; }
        public function setReplyTo($email, $name = null) { return $this; }
        public function getTransport() { return null; }
    }
    // Generated by Magento in production; its create() API constructs a fresh, non-shared builder.
    class TransportBuilderFactory {
        public function create(array $data = []) { return new TransportBuilder(); }
    }
}
namespace Coresh\QuoteRequest\Model {
    // Magento normally generates this factory; this isolated double mirrors only its create API.
    class QuoteRequestFactory { public function create(array $data = []) { return new QuoteRequest($data['data'] ?? []); } }
}
namespace Magento\Framework\App\Config {
    // Minimal parent contract for pure module config validation; no native save/events are emulated.
    class Value extends \Magento\Framework\DataObject {
        public function beforeSave() { return $this; }
        public function getFieldsetDataValue($key) {
            $data = $this->getData('fieldset_data');
            return is_array($data) && isset($data[$key]) ? $data[$key] : null;
        }
    }
}
