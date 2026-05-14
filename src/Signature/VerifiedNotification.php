<?php

declare(strict_types=1);

namespace Tegro\Purchase\Signature;

use Tegro\Purchase\Enum\PaymentStatus;

/**
 * A Tegro.Money webhook payload after signature verification. All fields
 * have already been validated for presence and basic shape — consumers
 * may rely on the types without re-checking.
 */
final readonly class VerifiedNotification
{
    public function __construct(
        public string $shopId,
        public string $orderId,
        public float $amount,
        public string $currency,
        public string $paymentSystem,
        public ?string $paymentId,
        public PaymentStatus $status,
        public bool $isTest,
    ) {
    }
}
