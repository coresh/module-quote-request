<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Migration;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\ModuleListInterface;

/** Reports verified adapters separately from extensions with an unknown schema. */
class LegacySourceDetector
{
    private const REQUIRED_COLUMNS = ['id', 'product_id', 'product_name', 'customer_name',
        'customer_email', 'customer_telephone', 'request_detail', 'status'];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ModuleListInterface $modules
    ) {
    }

    public function hasSupportedSource(): bool
    {
        return $this->getVerifiedSourceStatus() === true;
    }

    /** Null means absent; false means the fixed source exists but its schema is unverified. */
    private function getVerifiedSourceStatus(): ?bool
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('darsh_callprice');
        if (!$connection->isTableExists($table)) {
            return null;
        }
        return array_diff(self::REQUIRED_COLUMNS, array_keys($connection->describeTable($table))) === [];
    }

    /** @return list<array{name:string,supported:bool}> */
    public function getSources(): array
    {
        $sources = [];
        $supported = $this->getVerifiedSourceStatus();
        if ($supported !== null) {
            $sources[] = ['name' => 'Legacy quote request data', 'supported' => $supported];
        }
        foreach (['Sparsh_ProductInquiry', 'Sparsh_ProductInquiryPro'] as $name) {
            if ($this->modules->getOne($name) !== null) {
                $sources[] = ['name' => $name, 'supported' => false];
            }
        }
        return $sources;
    }
}
