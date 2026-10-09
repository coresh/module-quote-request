<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Migration;

/** Session-bound approval is consumed by the admin controller before execution. */
class MigrationApproval
{
    public function create(int $storeId, ?int $now = null): array
    {
        if ($storeId < 1) {
            throw new \InvalidArgumentException('A storefront store is required.');
        }
        $now ??= time();
        $token = bin2hex(random_bytes(32));
        return ['token' => $token, 'state' => [
            'store_id' => $storeId, 'token_hash' => hash('sha256', $token),
            'created_at' => $now, 'expires_at' => $now + 600
        ]];
    }

    public function validate(mixed $token, int $storeId, mixed $state, ?int $now = null): bool
    {
        $now ??= time();
        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
            && is_array($state) && $storeId > 0 && ($state['store_id'] ?? null) === $storeId
            && is_int($state['created_at'] ?? null) && is_int($state['expires_at'] ?? null)
            && $state['created_at'] <= $now && $state['expires_at'] >= $now
            && is_string($state['token_hash'] ?? null)
            && hash_equals($state['token_hash'], hash('sha256', $token));
    }
}
