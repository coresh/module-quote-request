<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\DB\Sql\Expression;

class QuoteRequest extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('coresh_quote_request', 'request_id');
    }

    /** Read only after the winner's insert commits; never returns data to an unverified caller. */
    public function findByIdempotencyKey(string $key): array|false
    {
        $connection = $this->getConnection();
        return $connection->fetchRow(
            $connection->select()->from($this->getMainTable())->where('idempotency_key = ?', $key)->limit(1)
        );
    }

    /** Return an atomic attempt number; false means another worker holds the send lease. */
    public function claimEmailAttempt(int $requestId): int|false
    {
        $connection = $this->getConnection();
        $now = gmdate('Y-m-d H:i:s');
        $expired = gmdate('Y-m-d H:i:s', time() - 600);
        $connection->beginTransaction();
        try {
            $changed = $connection->update(
                $this->getMainTable(),
                [
                    'email_status' => 'sending',
                    'email_attempts' => new Expression('email_attempts + 1'),
                    'last_error_code' => null,
                    'email_lease_started_at' => $now,
                    'updated_at' => $now
                ],
                [
                    'request_id = ?' => $requestId,
                    '(email_status <> \'sending\' OR email_lease_started_at IS NULL OR email_lease_started_at < ?)' => $expired
                ]
            );
            if ((int) $changed !== 1) {
                $connection->commit();
                return false;
            }
            // UPDATE holds the row lock until commit, so this is exactly the attempt just acquired.
            $attempt = (int) $connection->fetchOne(
                $connection->select()->from($this->getMainTable(), ['email_attempts'])
                    ->where('request_id = ?', $requestId)
            );
            $connection->commit();
            return $attempt;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /** Admin status changes must not overwrite transport state updated by another worker. */
    public function updateRequestStatus(int $requestId, string $status): void
    {
        if ($requestId < 1 || !in_array($status, ['new', 'processing', 'closed'], true)) {
            throw new \InvalidArgumentException('Unsupported request state.');
        }
        $this->getConnection()->update(
            $this->getMainTable(),
            ['request_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['request_id = ?' => $requestId]
        );
    }

    /** Update one bounded batch without loading/stomping any email or customer fields. */
    public function updateRequestStatuses(array $requestIds, string $status): int
    {
        if (!in_array($status, ['new', 'processing', 'closed'], true) || count($requestIds) > 1000) {
            throw new \InvalidArgumentException('Unsupported request state or batch size.');
        }
        $ids = [];
        foreach ($requestIds as $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/\A[1-9][0-9]{0,9}\z/', (string) $id)
                || (strlen((string) $id) === 10 && strcmp((string) $id, '4294967295') > 0)
            ) {
                throw new \InvalidArgumentException('Invalid request reference.');
            }
            $ids[(int) $id] = (int) $id;
        }
        if ($ids === []) {
            return 0;
        }
        return (int) $this->getConnection()->update(
            $this->getMainTable(),
            ['request_status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['request_id IN (?)' => array_values($ids)]
        );
    }

    /** Persist transport state separately from the customer enquiry's handling status. */
    public function completeEmailAttempt(
        int $requestId,
        string $status,
        ?string $errorCode = null,
        ?int $attempt = null
    ): void
    {
        if (!in_array($status, ['sent', 'failed'], true)) {
            throw new \InvalidArgumentException('Unsupported email state.');
        }
        $data = [
            'email_status' => $status,
            'last_error_code' => $errorCode,
            'email_lease_started_at' => null,
            'updated_at' => gmdate('Y-m-d H:i:s')
        ];
        if ($status === 'sent') {
            $data['last_email_sent_at'] = gmdate('Y-m-d H:i:s');
        }
        $where = ['request_id = ?' => $requestId];
        if ($attempt !== null) {
            $where['email_attempts = ?'] = $attempt;
            $where['email_status = ?'] = 'sending';
        }
        $this->getConnection()->update($this->getMainTable(), $data, $where);
    }
}
