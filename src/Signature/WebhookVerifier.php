<?php

declare(strict_types=1);

namespace Tegro\Purchase\Signature;

use Tegro\Purchase\Enum\PaymentStatus;
use Tegro\Purchase\Exception\WebhookSignatureException;

/**
 * Verifier for Tegro.Money payment-notification webhooks.
 *
 * Signature scheme (per tegro.money/docs/en/payments/signature/):
 *   1. Drop the `sign` field from the incoming form data.
 *   2. Sort the remaining fields alphabetically by key.
 *   3. URL-encode them as a query string in PHP's `http_build_query` shape.
 *   4. expected = md5(query . SECRET_KEY)   // lowercase hex
 *   5. Constant-time compare expected vs the provided `sign`.
 *
 * The MD5 weakness is intentional and required by the upstream API. Do not
 * "upgrade" it to SHA-256 — that would break compatibility with the server.
 */
final readonly class WebhookVerifier
{
    public function __construct(private string $secretKey)
    {
        if ($this->secretKey === '') {
            throw new \InvalidArgumentException('secretKey must not be empty');
        }
    }

    /**
     * @param array<string, scalar|null> $rawFields raw $_POST shape
     */
    public function verify(array $rawFields): VerifiedNotification
    {
        $providedSign = $rawFields['sign'] ?? null;
        if (!is_string($providedSign) || $providedSign === '') {
            throw new WebhookSignatureException(WebhookSignatureException::REASON_MISSING_SIGN);
        }

        /** @var array<string, string> $fieldsForSign */
        $fieldsForSign = [];
        foreach ($rawFields as $key => $value) {
            if ($key === 'sign') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            // Coerce scalars to string the same way PHP's http_build_query does.
            $fieldsForSign[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        ksort($fieldsForSign);
        $query = http_build_query($fieldsForSign, '', '&', PHP_QUERY_RFC1738);
        $expected = strtolower(md5($query . $this->secretKey));
        $got = strtolower($providedSign);

        if (strlen($expected) !== strlen($got) || !hash_equals($expected, $got)) {
            throw new WebhookSignatureException(WebhookSignatureException::REASON_BAD_SIGNATURE);
        }

        $shopId        = $this->requireString($rawFields, 'shop_id');
        $orderId       = $this->requireString($rawFields, 'order_id');
        $amountStr     = $this->requireString($rawFields, 'amount');
        $currencyRaw   = $this->stringOrEmpty($rawFields, 'currency');
        $currency      = $currencyRaw === '' ? 'RUB' : $currencyRaw;
        $paymentSystem = $this->stringOrEmpty($rawFields, 'payment_system');
        $paymentId     = $this->stringOrEmpty($rawFields, 'payment_id');
        $statusRaw     = $rawFields['status'] ?? null;
        $isTest        = ($rawFields['test'] ?? null) === '1' || ($rawFields['test'] ?? null) === 'true';

        if (!is_numeric($amountStr)) {
            throw new WebhookSignatureException(
                WebhookSignatureException::REASON_BAD_FIELD_VALUE,
                'amount must be numeric',
            );
        }
        $amount = (float) $amountStr;
        if ($amount <= 0.0) {
            throw new WebhookSignatureException(
                WebhookSignatureException::REASON_BAD_FIELD_VALUE,
                'amount must be > 0',
            );
        }

        try {
            $status = $statusRaw === null ? PaymentStatus::Paid : PaymentStatus::fromMixed($statusRaw);
        } catch (\ValueError $e) {
            throw new WebhookSignatureException(
                WebhookSignatureException::REASON_BAD_FIELD_VALUE,
                'status: ' . $e->getMessage(),
            );
        }

        return new VerifiedNotification(
            shopId:        $shopId,
            orderId:       $orderId,
            amount:        $amount,
            currency:      $currency,
            paymentSystem: $paymentSystem,
            paymentId:     $paymentId === '' ? null : $paymentId,
            status:        $status,
            isTest:        $isTest,
        );
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    private function requireString(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new WebhookSignatureException(
                WebhookSignatureException::REASON_MISSING_REQUIRED_FIELD,
                $key,
            );
        }
        return $value;
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    private function stringOrEmpty(array $fields, string $key): string
    {
        $value = $fields[$key] ?? null;
        return is_string($value) ? $value : '';
    }
}
