<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Plugin\Adminhtml;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/** Hidden listing columns are still serialized by the core data provider. */
class ProtectPrivateGridFields
{
    private const DATA_SOURCE = 'coresh_quote_request_listing_data_source';
    private const PRIVATE_FIELDS = ['idempotency_key', 'payload_hash'];

    public function afterGetData(DataProvider $subject, array $result): array
    {
        if ($subject->getName() !== self::DATA_SOURCE || !isset($result['items'])
            || !is_array($result['items'])
        ) {
            return $result;
        }
        foreach ($result['items'] as &$item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (self::PRIVATE_FIELDS as $field) {
                unset($item[$field]);
            }
        }
        unset($item);
        return $result;
    }
}
