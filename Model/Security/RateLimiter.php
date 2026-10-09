<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Security;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/** A fixed-window database counter shared by workers and web nodes. */
class RateLimiter
{
    public function __construct(private readonly ResourceConnection $resource, private readonly Config $config)
    {
    }

    public function consume(int $storeId, string $remoteAddress): void
    {
        if (!$this->config->isFlag('security/rate_limit_enabled', $storeId)) {
            return;
        }
        $limit = max(1, min(1000, (int) $this->config->getValue('security/rate_limit_max', $storeId) ?: 5));
        $window = max(1, min(86400, (int) $this->config->getValue('security/rate_limit_window', $storeId) ?: 600));
        $now = time();
        $windowStart = intdiv($now, $window) * $window;
        // REMOTE_ADDR only: proxy-specific trusted address resolution belongs to the deployment.
        $ip = filter_var($remoteAddress, FILTER_VALIDATE_IP) ? $remoteAddress : 'unknown';
        $key = hash('sha256', $storeId . ':' . $ip . ':' . $window . ':' . $windowStart);
        $connection = $this->resource->getConnection();
        $table = $connection->quoteIdentifier($this->resource->getTableName('coresh_quote_request_rate_limit'));
        // Own fixed identifiers only; all request-derived values are bound parameters.
        $connection->query(
            'INSERT INTO ' . $table . ' (bucket_key, hits, expires_at) VALUES (?, 1, ?) '
            . 'ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$key, $windowStart + $window]
        );
        $hits = (int) $connection->fetchOne('SELECT hits FROM ' . $table . ' WHERE bucket_key = ?', [$key]);
        $connection->delete(
            $this->resource->getTableName('coresh_quote_request_rate_limit'),
            ['expires_at < ?' => $now - 86400]
        );
        if ($hits > $limit) {
            throw new LocalizedException(__('Too many quote requests. Please try again later.'));
        }
    }
}
