<?php

declare(strict_types=1);

namespace Tegro\Purchase\Exception;

/**
 * Thrown by the webhook signature verifier when the incoming payload fails
 * any structural or cryptographic check. The `code` is a stable identifier
 * suitable for log filtering — do not parse the message.
 */
final class WebhookSignatureException extends \RuntimeException
{
    public const REASON_MISSING_SIGN = 'missing_sign';
    public const REASON_BAD_SIGNATURE = 'bad_signature';
    public const REASON_MISSING_REQUIRED_FIELD = 'missing_required_field';
    public const REASON_BAD_FIELD_VALUE = 'bad_field_value';

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct(
            $detail === '' ? "webhook_signature:{$reason}" : "webhook_signature:{$reason}: {$detail}",
        );
    }
}
