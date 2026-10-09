<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Block;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Element\Template;

/** Common FPC tag permits store/category-rule changes to invalidate cached presentation. */
class Modal extends Template implements IdentityInterface
{
    public function getIdentities(): array
    {
        return ['coresh_quote_request'];
    }
}
