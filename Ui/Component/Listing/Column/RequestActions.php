<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class RequestActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (!empty($item['request_id'])) {
                    $item[$this->getData('name')]['view'] = [
                        'href' => $this->urlBuilder->getUrl('coresh_quoterequest/request/view', ['request_id' => (int) $item['request_id']]),
                        'label' => __('View')
                    ];
                }
            }
        }
        return $dataSource;
    }
}
