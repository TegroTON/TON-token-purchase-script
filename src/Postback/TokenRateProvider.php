<?php

declare(strict_types=1);

namespace Tegro\Purchase\Postback;

/**
 * Source of the current fiat-per-token rate. Implementations may fetch from
 * a DEX aggregator, an off-chain oracle, or a hard-coded constant for tests.
 *
 * The contract: return a positive decimal string in the same fiat currency
 * as the order ("amount" field on the verified webhook). Throw on failure
 * — the Handler will refuse to credit if the rate is not available.
 */
interface TokenRateProvider
{
    public function currentRate(): string;
}
