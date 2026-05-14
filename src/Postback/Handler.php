<?php

declare(strict_types=1);

namespace Tegro\Purchase\Postback;

use Psr\Log\LoggerInterface;
use Tegro\Purchase\Config\Config;
use Tegro\Purchase\Enum\PaymentStatus;
use Tegro\Purchase\Exception\WebhookSignatureException;
use Tegro\Purchase\Repository\PaylinkRepository;
use Tegro\Purchase\Repository\UserRepository;
use Tegro\Purchase\Signature\WebhookVerifier;
use Tegro\Purchase\Telegram\TelegramClient;

/**
 * Glue that wires Tegro's POST notification through verification, idempotent
 * claim, balance credit, and a Telegram acknowledgement to the buyer.
 *
 * Order of operations is deliberate:
 *
 *   1. Verify signature first — refuse anonymous traffic before touching DB.
 *   2. Reject any non-Paid status with 200 OK (nothing to do, but ack so
 *      Tegro doesn't keep retrying).
 *   3. Parse order_id into (chatId, rowId).
 *   4. Atomically claim the paylink. If somebody else already claimed it
 *      (duplicate retry from Tegro, replay, etc.), return 200 OK and stop —
 *      *do not* credit twice.
 *   5. Compute token amount, credit the user, send a Telegram message.
 *
 * Failures inside step 5 do *not* roll back the paylink claim — the design
 * choice is "at-most-once credit" rather than "exactly-once but riskier".
 * Operators inspect logs for partial states.
 */
final readonly class Handler
{
    public function __construct(
        private WebhookVerifier $verifier,
        private PaylinkRepository $paylinks,
        private UserRepository $users,
        private TelegramClient $telegram,
        private TokenRateProvider $rates,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, scalar|null> $rawFields raw $_POST
     */
    public function handle(array $rawFields): Response
    {
        try {
            $verified = $this->verifier->verify($rawFields);
        } catch (WebhookSignatureException $e) {
            $this->logger->warning('webhook rejected', ['reason' => $e->reason]);
            return new Response(403, 'forbidden');
        }

        if ($verified->status !== PaymentStatus::Paid) {
            $this->logger->info('webhook acked (non-Paid status)', [
                'order_id' => $verified->orderId,
                'status' => $verified->status->name,
                'is_test' => $verified->isTest,
            ]);
            return new Response(200, 'ok');
        }

        if ($verified->isTest) {
            $this->logger->info('webhook acked (test mode)', ['order_id' => $verified->orderId]);
            return new Response(200, 'ok');
        }

        try {
            [$chatId, $rowId] = OrderIdParser::parse($verified->orderId);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('bad order_id shape', [
                'order_id' => $verified->orderId,
                'error' => $e->getMessage(),
            ]);
            return new Response(400, 'bad_order_id');
        }

        $claimed = $this->paylinks->claimPaid($rowId, $chatId);
        if (!$claimed) {
            $this->logger->info('paylink already claimed (or absent) — noop', [
                'order_id' => $verified->orderId,
            ]);
            return new Response(200, 'ok');
        }

        try {
            $rate = $this->rates->currentRate();
            $tokenAmount = TokenAmountCalculator::calculate(
                fiatAmount:     number_format($verified->amount, 9, '.', ''),
                tokenRate:      $rate,
                markupPercent:  $this->config->markupPercent,
            );
        } catch (\Throwable $e) {
            $this->logger->error('token amount calculation failed after claim', [
                'order_id' => $verified->orderId,
                'error' => $e->getMessage(),
            ]);
            return new Response(500, 'rate_unavailable');
        }

        $credited = $this->users->creditBalance($chatId, $tokenAmount);
        if (!$credited) {
            $this->logger->error('paylink claimed but user row missing', [
                'order_id' => $verified->orderId,
                'chat_id' => $chatId,
            ]);
            return new Response(500, 'user_missing');
        }

        $this->sendBuyerNotification($chatId, $verified, $tokenAmount);

        $this->logger->info('payment processed', [
            'order_id' => $verified->orderId,
            'chat_id' => $chatId,
            'amount_fiat' => $verified->amount,
            'amount_tokens' => $tokenAmount,
            'currency' => $verified->currency,
        ]);

        return new Response(200, 'ok');
    }

    private function sendBuyerNotification(
        string $chatId,
        \Tegro\Purchase\Signature\VerifiedNotification $verified,
        string $tokenAmount,
    ): void {
        $message = sprintf(
            '✅ Payment of %s %s received. %s %s credited to your balance.',
            self::escape(number_format($verified->amount, 2, '.', '')),
            self::escape($verified->currency),
            self::escape($tokenAmount),
            self::escape($this->config->tokenSymbol),
        );
        try {
            $this->telegram->sendMessage($chatId, $message);
        } catch (\Throwable $e) {
            $this->logger->warning('telegram notification failed (balance already credited)', [
                'order_id' => $verified->orderId,
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
