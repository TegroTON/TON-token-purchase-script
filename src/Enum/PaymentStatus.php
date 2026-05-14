<?php

declare(strict_types=1);

namespace Tegro\Purchase\Enum;

/**
 * Tegro.Money payment status as published in the official docs at
 * https://tegro.money/docs/en/payments/notify/.
 *
 * Values are kept as plain integers because the API ships them that way over
 * the wire — wrapping the numeric form in an enum keeps the magic numbers
 * from leaking into the rest of the codebase.
 */
enum PaymentStatus: int
{
    case New = 0;
    case Paid = 1;
    case Failed = 2;
    case Refunded = 3;
    case Pending = 4;

    public static function fromMixed(mixed $value): self
    {
        if (is_int($value)) {
            return self::from($value);
        }
        if (is_string($value) && ctype_digit($value)) {
            return self::from((int) $value);
        }
        throw new \ValueError(sprintf('Unsupported payment status value: %s', var_export($value, true)));
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Failed, self::Refunded => true,
            self::New, self::Pending => false,
        };
    }
}
