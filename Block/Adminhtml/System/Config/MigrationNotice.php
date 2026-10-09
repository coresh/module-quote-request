<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Block\Adminhtml\System\Config;

use Coresh\QuoteRequest\Model\Migration\LegacySourceDetector;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;

class MigrationNotice extends Field
{
    public function __construct(
        Context $context,
        private readonly LegacySourceDetector $detector,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $sources = $this->detector->getSources();
        if ($sources === []) {
            return $this->escapeHtml(__('No supported legacy source was detected.'));
        }
        $html = '<ul>';
        foreach ($sources as $source) {
            $html .= '<li>' . $this->escapeHtml($source['name']) . ': '
                . $this->escapeHtml($source['supported']
                    ? __('Verified Darsh data adapter available.')
                    : __('Automatic import unavailable: the source schema has not been verified.')) . '</li>';
        }
        $html .= '</ul>';
        if ($this->detector->hasSupportedSource()
            && $this->authorization->isAllowed('Coresh_QuoteRequest::migrate')
        ) {
            $html .= '<a class="action-default" href="'
                . $this->escapeUrl($this->getUrl('coresh_quoterequest/migration/index')) . '">'
                . $this->escapeHtml(__('Preview / Migrate Darsh data')) . '</a>';
        }
        return $html;
    }
}
