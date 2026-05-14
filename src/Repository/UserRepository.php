<?php

declare(strict_types=1);

namespace Tegro\Purchase\Repository;

/**
 * Users keep per-chat token balances. The credit operation uses a single
 * UPDATE statement (no read-modify-write) so it is safe against concurrent
 * crediting from multiple webhooks for the same chat.
 */
final readonly class UserRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Credit `amount` tokens (decimal string) to the user identified by chatId.
     * The decimal string is concatenated server-side via CAST to keep precision.
     * Returns true if a row was updated.
     */
    public function creditBalance(string $chatId, string $amount): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
                SET balance = balance + CAST(:amount AS DECIMAL(30,9))
              WHERE chatid = :chatid',
        );
        $stmt->bindValue(':amount', $amount, \PDO::PARAM_STR);
        $stmt->bindValue(':chatid', $chatId, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() === 1;
    }

    public function exists(string $chatId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE chatid = :chatid LIMIT 1');
        $stmt->bindValue(':chatid', $chatId, \PDO::PARAM_STR);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
