<?php

declare(strict_types=1);

namespace Tegro\Purchase\Postback;

/**
 * Converts a fiat amount into a token amount given a token rate and a
 * markup percentage. Math is done in BCMath to avoid binary-float drift
 * when crediting customer balances — the result is a decimal string
 * suitable for direct insertion into a DECIMAL(30,9) column.
 *
 * Formula:
 *     tokensGross   = fiatAmount / tokenRate
 *     tokensCredited = tokensGross * 100 / markupPercent
 *
 * Example with markupPercent=110, fiat=110, rate=1: tokensCredited = 100.
 * Customer paid 10% premium over the upstream rate.
 */
final class TokenAmountCalculator
{
    private const SCALE = 9;

    public static function calculate(
        string $fiatAmount,
        string $tokenRate,
        float $markupPercent,
    ): string {
        if (!is_numeric($fiatAmount) || (float) $fiatAmount <= 0.0) {
            throw new \InvalidArgumentException('fiatAmount must be a positive number');
        }
        if (!is_numeric($tokenRate) || (float) $tokenRate <= 0.0) {
            throw new \InvalidArgumentException('tokenRate must be a positive number');
        }
        if ($markupPercent <= 0.0) {
            throw new \InvalidArgumentException('markupPercent must be > 0');
        }
        if (!extension_loaded('bcmath')) {
            throw new \RuntimeException('ext-bcmath is required for accurate fiat→token math');
        }

        $gross = bcdiv($fiatAmount, $tokenRate, self::SCALE);
        $hundred = bcmul($gross, '100', self::SCALE);
        $markupStr = number_format($markupPercent, self::SCALE, '.', '');
        return bcdiv($hundred, $markupStr, self::SCALE);
    }
}
