<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Block\Adminhtml;

use Coresh\QuoteRequest\Model\Migration\LegacySourceDetector;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session;
use Magento\Store\Model\StoreManagerInterface;

class Migration extends Template
{
    public function __construct(
        Context $context,
        private readonly LegacySourceDetector $detector,
        private readonly StoreManagerInterface $stores,
        private readonly Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSources(): array { return $this->detector->getSources(); }
    public function hasSupportedSource(): bool { return $this->detector->hasSupportedSource(); }
    public function getStoreOptions(): array
    {
        $options = [];
        foreach ($this->stores->getStores() as $store) {
            if ((int) $store->getId() > 0 && $store->isActive()) {
                $options[] = ['value' => (int) $store->getId(), 'label' => (string) $store->getName()];
            }
        }
        return $options;
    }
    public function getPreview(): array
    {
        $value = $this->session->getData('coresh_quote_request_migration_preview');
        return is_array($value) ? $value : [];
    }
}
