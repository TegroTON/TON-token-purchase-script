<?php

declare(strict_types=1);

namespace Tegro\Purchase\Tests\Postback;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tegro\Purchase\Postback\TokenAmountCalculator;

#[CoversClass(TokenAmountCalculator::class)]
final class TokenAmountCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) {
            $this->markTestSkipped('ext-bcmath not available');
        }
    }

    public function testNoMarkup(): void
    {
        $result = TokenAmountCalculator::calculate(
            fiatAmount: '100',
            tokenRate: '1',
            markupPercent: 100.0,
        );
        $this->assertSame('100.000000000', $result);
    }

    public function testTenPercentMarkup(): void
    {
        // 110 fiat / 1 rate = 110 gross; 110 * 100 / 110% = 100 tokens credited.
        $result = TokenAmountCalculator::calculate(
            fiatAmount: '110',
            tokenRate: '1',
            markupPercent: 110.0,
        );
        $this->assertSame('100.000000000', $result);
    }

    public function testPrecisionRetained(): void
    {
        // 1 fiat / 3 rate = 0.333... gross; markup 100 keeps it identical.
        $result = TokenAmountCalculator::calculate(
            fiatAmount: '1',
            tokenRate: '3',
            markupPercent: 100.0,
        );
        $this->assertSame('0.333333333', $result);
    }

    public function testRejectsNonPositiveAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TokenAmountCalculator::calculate('0', '1', 100.0);
    }

    public function testRejectsZeroRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TokenAmountCalculator::calculate('100', '0', 100.0);
    }

    public function testRejectsNonPositiveMarkup(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TokenAmountCalculator::calculate('100', '1', 0.0);
    }
}
