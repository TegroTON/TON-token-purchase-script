<?php

declare(strict_types=1);

namespace Tegro\Purchase\Postback;

/**
 * Paylinks encode two pieces of state in Tegro's `order_id` field:
 *   "<chatid>:<rowid>"
 *
 * Both halves must be non-empty and contain only safe characters — the
 * verifier has already checked the signature, but we still validate the
 * structure so that broken integrations fail loudly instead of writing
 * garbage IDs to the database.
 */
final class OrderIdParser
{
    /**
     * @return array{0:string, 1:string} [chatId, rowId]
     */
    public static function parse(string $orderId): array
    {
        if ($orderId === '' || !str_contains($orderId, ':')) {
            throw new \InvalidArgumentException(
                'order_id must have shape "<chatid>:<rowid>"',
            );
        }
        [$chatId, $rowId] = explode(':', $orderId, 2);
        if ($chatId === '' || $rowId === '') {
            throw new \InvalidArgumentException(
                'order_id parts must be non-empty',
            );
        }
        if (!preg_match('/^-?[A-Za-z0-9_-]{1,64}$/', $chatId)) {
            throw new \InvalidArgumentException('order_id chatId has bad chars');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $rowId)) {
            throw new \InvalidArgumentException('order_id rowId has bad chars');
        }
        return [$chatId, $rowId];
    }
}
