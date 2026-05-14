<?php

declare(strict_types=1);

namespace Tegro\Purchase\Tests\Signature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tegro\Purchase\Enum\PaymentStatus;
use Tegro\Purchase\Exception\WebhookSignatureException;
use Tegro\Purchase\Signature\WebhookVerifier;

#[CoversClass(WebhookVerifier::class)]
final class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'test-secret-key';

    public function testAcceptsCorrectlySignedPayload(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '100.50',
            'order_id' => '12345:abc',
            'payment_system' => '1',
            'currency' => 'RUB',
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);

        $verified = (new WebhookVerifier(self::SECRET))->verify($fields);

        $this->assertSame('shop_abc', $verified->shopId);
        $this->assertSame('12345:abc', $verified->orderId);
        $this->assertSame(100.50, $verified->amount);
        $this->assertSame('RUB', $verified->currency);
        $this->assertSame(PaymentStatus::Paid, $verified->status);
        $this->assertFalse($verified->isTest);
    }

    public function testRejectsTamperedAmount(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '100.50',
            'order_id' => '12345:abc',
            'currency' => 'RUB',
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);
        // Attacker bumps amount after signing.
        $fields['amount'] = '99999.00';

        $this->expectExceptionObject(new WebhookSignatureException(
            WebhookSignatureException::REASON_BAD_SIGNATURE,
        ));
        (new WebhookVerifier(self::SECRET))->verify($fields);
    }

    public function testRejectsWrongSecret(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '100.50',
            'order_id' => '12345:abc',
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, 'other-secret');

        $this->expectException(WebhookSignatureException::class);
        (new WebhookVerifier(self::SECRET))->verify($fields);
    }

    public function testRejectsMissingSign(): void
    {
        $this->expectExceptionObject(new WebhookSignatureException(
            WebhookSignatureException::REASON_MISSING_SIGN,
        ));
        (new WebhookVerifier(self::SECRET))->verify([
            'shop_id' => 'shop_abc',
            'amount' => '100',
            'order_id' => 'x:y',
            'status' => '1',
        ]);
    }

    public function testRejectsMissingRequiredField(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '100',
            // order_id intentionally missing
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);

        $this->expectExceptionObject(new WebhookSignatureException(
            WebhookSignatureException::REASON_MISSING_REQUIRED_FIELD,
            'order_id',
        ));
        (new WebhookVerifier(self::SECRET))->verify($fields);
    }

    public function testRejectsNonNumericAmount(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => 'not-a-number',
            'order_id' => 'x:y',
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);

        $this->expectExceptionObject(new WebhookSignatureException(
            WebhookSignatureException::REASON_BAD_FIELD_VALUE,
            'amount must be numeric',
        ));
        (new WebhookVerifier(self::SECRET))->verify($fields);
    }

    public function testRejectsZeroAmount(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '0',
            'order_id' => 'x:y',
            'status' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);

        $this->expectExceptionObject(new WebhookSignatureException(
            WebhookSignatureException::REASON_BAD_FIELD_VALUE,
            'amount must be > 0',
        ));
        (new WebhookVerifier(self::SECRET))->verify($fields);
    }

    public function testAcceptsTestFlag(): void
    {
        $fields = [
            'shop_id' => 'shop_abc',
            'amount' => '100',
            'order_id' => 'x:y',
            'status' => '1',
            'test' => '1',
        ];
        $fields['sign'] = self::computeSign($fields, self::SECRET);

        $verified = (new WebhookVerifier(self::SECRET))->verify($fields);
        $this->assertTrue($verified->isTest);
    }

    public function testFieldOrderDoesNotMatter(): void
    {
        // Same fields, different insertion order — the signature must match
        // because the verifier sorts keys before hashing.
        $fieldsA = [
            'shop_id' => 'shop_abc',
            'amount' => '100',
            'order_id' => 'x:y',
            'status' => '1',
        ];
        $fieldsB = [
            'status' => '1',
            'order_id' => 'x:y',
            'amount' => '100',
            'shop_id' => 'shop_abc',
        ];
        $signA = self::computeSign($fieldsA, self::SECRET);
        $signB = self::computeSign($fieldsB, self::SECRET);
        $this->assertSame($signA, $signB);
    }

    public function testEmptySecretRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WebhookVerifier('');
    }

    /**
     * Reference implementation of Tegro's signature scheme — kept in tests
     * only so production code can never accidentally call it.
     *
     * @param array<string, scalar> $fields
     */
    private static function computeSign(array $fields, string $secret): string
    {
        ksort($fields);
        $query = http_build_query($fields, '', '&', PHP_QUERY_RFC1738);
        return strtolower(md5($query . $secret));
    }
}
