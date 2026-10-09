<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\DataProvider;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/** Scoped to this module's Reporting instance; no global fulltext/index override. */
class FulltextFilter implements FilterApplierInterface
{
    private const FIELDS = [
        'customer_name', 'customer_email', 'customer_telephone', 'product_name', 'product_sku', 'message'
    ];

    public function apply(Collection $collection, Filter $filter)
    {
        if (!$collection instanceof AbstractDb || !is_string($filter->getValue())) {
            throw new LocalizedException(__('Please enter a valid search query.'));
        }
        $query = trim($filter->getValue());
        if (strlen($query) > 512) {
            throw new LocalizedException(__('The search query is too long.'));
        }
        if ($query === '') {
            return;
        }
        // Adapter prepares LIKE values with bound/quoted conditions. Treat %/_ as literal input.
        $value = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $collection->addFieldToFilter(
            array_map(static fn (string $field): string => 'main_table.' . $field, self::FIELDS),
            array_fill(0, count(self::FIELDS), ['like' => $value])
        );
    }
}
