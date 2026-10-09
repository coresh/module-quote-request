<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'request_id';

    protected function _construct(): void
    {
        $this->_init(
            \Coresh\QuoteRequest\Model\QuoteRequest::class,
            \Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest::class
        );
    }
}
