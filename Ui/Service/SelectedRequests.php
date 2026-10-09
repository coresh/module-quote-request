<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Service;

use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

/** Core selection semantics with module namespace, count and canonical-ID guards. */
class SelectedRequests
{
    public function __construct(
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly GridSelectionValidator $validator
    ) {
    }

    public function rows(): \Generator
    {
        $this->validator->validate([
            'namespace' => $this->request->getParam('namespace'),
            'selected' => $this->request->getParam('selected'),
            'excluded' => $this->request->getParam('excluded')
        ]);
        // Count the filtered selection before core Filter materializes all matching IDs.
        $component = $this->filter->getComponent();
        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $provider = $component->getContext()->getDataProvider();
        if ($provider->getSearchResult()->getTotalCount() > GridSelectionValidator::MAX_ROWS) {
            throw new LocalizedException(__('This operation is limited to 25,000 requests. Narrow the filters and try again.'));
        }
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        if ($collection->getSize() > GridSelectionValidator::MAX_ROWS) {
            throw new LocalizedException(__('This operation is limited to 25,000 requests. Narrow the filters and try again.'));
        }
        $base = clone $collection->getSelect();
        $base->reset(Select::ORDER)->order('main_table.request_id ASC')->limit(500);
        $cursor = 0;
        do {
            $select = clone $base;
            if ($cursor > 0) {
                $select->where('main_table.request_id > ?', $cursor);
            }
            $rows = $collection->getConnection()->fetchAll($select);
            foreach ($rows as $row) {
                $cursor = (int) $row['request_id'];
                yield $row;
            }
        } while (count($rows) === 500);
    }
}
