<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Eligibility;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;

/** ISO dates are inclusive local calendar dates, never server-local dates. */
class DateRangePolicy
{
    public function __construct(private readonly Config $config, private readonly TimezoneInterface $timezone)
    {
    }

    public function isAllowed(int $storeId, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->config->isFlag('general/use_date_range', $storeId)) {
            return true;
        }
        $bounds = $this->getBounds($storeId);
        if ($bounds === null) {
            return false;
        }
        [$from, $until] = $bounds;
        $instant = ($now ?? new \DateTimeImmutable('now'))->getTimestamp();
        return ($from === null || $instant >= $from) && ($until === null || $instant < $until);
    }

    /** Expire cached HTML at the next eligibility boundary, including before the period starts. */
    public function clampTtl(int $storeId, int $ttl, ?\DateTimeImmutable $now = null): int
    {
        if (!$this->config->isEnabled($storeId) || !$this->config->isFlag('general/use_date_range', $storeId)) {
            return $ttl;
        }
        $bounds = $this->getBounds($storeId);
        if ($bounds === null) {
            return min($ttl, 1);
        }
        $instant = ($now ?? new \DateTimeImmutable('now'))->getTimestamp();
        foreach ($bounds as $boundary) {
            if ($boundary !== null && $boundary > $instant) {
                $ttl = min($ttl, max(1, $boundary - $instant));
            }
        }
        return $ttl;
    }

    /** @return array{?int, ?int}|null Invalid config fails closed. */
    private function getBounds(int $storeId): ?array
    {
        try {
            $timezone = new \DateTimeZone((string) $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, $storeId));
            $fromText = trim($this->config->getValue('general/from_date', $storeId));
            $toText = trim($this->config->getValue('general/to_date', $storeId));
            $from = $fromText === '' ? null : $this->parse($fromText, $timezone);
            $to = $toText === '' ? null : $this->parse($toText, $timezone);
            if (($fromText !== '' && $from === null) || ($toText !== '' && $to === null)) {
                return null;
            }
            if ($from !== null && $to !== null && $from > $to) {
                return null;
            }
            return [$from?->getTimestamp(), $to?->modify('+1 day')->getTimestamp()];
        } catch (\Exception) {
            return null;
        }
    }

    private function parse(string $date, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        // Validate syntax before PHP's parser, which throws ValueError for embedded null bytes.
        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) !== 1) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }
}
