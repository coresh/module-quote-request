<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Ui\Service;

use Magento\Framework\Exception\LocalizedException;

/** Accept only this listing, the standard selected/excluded protocol and bounded ID lists. */
class GridSelectionValidator
{
    public const NAMESPACE = 'coresh_quote_request_listing';
    public const MAX_ROWS = 25000;

    public function validate(array $params): array
    {
        if (($params['namespace'] ?? null) !== self::NAMESPACE) {
            $this->reject();
        }
        $selected = $params['selected'] ?? null;
        $excluded = $params['excluded'] ?? null;
        if ($selected !== null && $excluded !== null) {
            $this->reject();
        }
        if ($selected !== null && !is_array($selected)) {
            $this->reject();
        }
        if ($excluded !== null && $excluded !== 'false' && !is_array($excluded)) {
            $this->reject();
        }
        $selected = $selected === null ? null : $this->ids($selected);
        $excluded = is_array($excluded) ? $this->ids($excluded) : $excluded;
        if ($excluded !== 'false' && empty($selected) && empty($excluded)) {
            $this->reject();
        }
        return ['namespace' => self::NAMESPACE, 'selected' => $selected, 'excluded' => $excluded];
    }

    private function ids(array $values): array
    {
        if (count($values) > self::MAX_ROWS) {
            throw new LocalizedException(__('Too many requests selected. Narrow the filters and try again.'));
        }
        $ids = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_string($value)) {
                $this->reject();
            }
            $id = (string) $value;
            if (preg_match('/\A[1-9][0-9]{0,9}\z/D', $id) !== 1
                || (strlen($id) === 10 && strcmp($id, '4294967295') > 0)
            ) {
                $this->reject();
            }
            $ids[(int) $id] = (int) $id;
        }
        return array_values($ids);
    }

    private function reject(): never
    {
        throw new LocalizedException(__('Please select valid quote requests from this listing.'));
    }
}
