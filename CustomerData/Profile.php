<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session;

/** Private content: never rendered into the cached product/category HTML. */
class Profile implements SectionSourceInterface
{
    public function __construct(private readonly Session $customerSession)
    {
    }

    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['name' => '', 'email' => ''];
        }
        $customer = $this->customerSession->getCustomerDataObject();
        return [
            'name' => trim((string) $customer->getFirstname() . ' ' . (string) $customer->getLastname()),
            'email' => (string) $customer->getEmail()
        ];
    }
}
