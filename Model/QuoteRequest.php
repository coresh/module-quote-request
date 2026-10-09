<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model;

use Magento\Framework\Model\AbstractModel;

/** Customer enquiry; independent of Magento's checkout quote. */
class QuoteRequest extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\QuoteRequest::class);
    }
}
