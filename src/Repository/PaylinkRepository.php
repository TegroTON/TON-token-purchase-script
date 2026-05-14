<?php

declare(strict_types=1);

namespace Tegro\Purchase\Repository;

/**
 * Paylinks own the idempotency boundary of the whole flow. The "claim"
 * operation is a single-statement conditional UPDATE — it can only succeed
 * once per (rowid, chatid) pair, so concurrent or replayed webhooks for
 * the same order will all see `false` after the first writer wins.
 */
final readonly class PaylinkRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Atomically transition a paylink from "pending" to "paid".
     * Returns true exactly once per paylink — subsequent calls return false.
     */
    public function claimPaid(string $rowId, string $chatId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE paylinks
                SET status = 1, paid_at = CURRENT_TIMESTAMP
              WHERE rowid = :rowid
                AND chatid = :chatid
                AND status = 0',
        );
        $stmt->bindValue(':rowid', $rowId, \PDO::PARAM_STR);
        $stmt->bindValue(':chatid', $chatId, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }
}
