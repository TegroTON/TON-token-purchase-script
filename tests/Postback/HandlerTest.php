<?php

declare(strict_types=1);

namespace Tegro\Purchase\Tests\Postback;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tegro\Purchase\Config\Config;
use Tegro\Purchase\Postback\Handler;
use Tegro\Purchase\Postback\Response;
use Tegro\Purchase\Postback\TokenRateProvider;
use Tegro\Purchase\Repository\PaylinkRepository;
use Tegro\Purchase\Repository\UserRepository;
use Tegro\Purchase\Signature\WebhookVerifier;
use Tegro\Purchase\Telegram\TelegramClient;

#[CoversClass(Handler::class)]
final class HandlerTest extends TestCase
{
    private const SECRET = 'test-secret';

    private \PDO $pdo;
    private int $telegramCalls = 0;

    protected function setUp(): void
    {
        if (!extension_loaded('bcmath')) {
            $this->markTestSkipped('ext-bcmath not available');
        }
        // SQLite syntax differs from MySQL in a few places — the handler
        // doesn't care about the dialect, only that prepared params work and
        // that rowCount() is reliable after UPDATE. Both are true on SQLite.
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec(
            'CREATE TABLE paylinks (
                rowid TEXT NOT NULL,
                chatid TEXT NOT NULL,
                status INTEGER NOT NULL DEFAULT 0,
                paid_at TEXT,
                PRIMARY KEY (rowid, chatid)
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE users (
                chatid TEXT PRIMARY KEY,
                balance NUMERIC NOT NULL DEFAULT 0
            )',
        );
        $this->telegramCalls = 0;
    }

    public function testCreditsBalanceOnFirstPaidWebhook(): void
    {
        $this->seed('chat42', 'order7', userBalance: '5');
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '110',
            'order_id' => 'chat42:order7',
            'status' => '1',
            'currency' => 'RUB',
        ]);

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('ok', $response->body);
        $this->assertSame('105.000000000', $this->fetchBalance('chat42'));
        $this->assertSame(1, $this->fetchStatus('chat42', 'order7'));
        $this->assertSame(1, $this->telegramCalls);
    }

    public function testDuplicateWebhookCreditsOnce(): void
    {
        $this->seed('chat42', 'order7', userBalance: '0');
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => 'chat42:order7',
            'status' => '1',
        ]);

        $h = $this->handler('1.0');
        $first = $h->handle($fields);
        $second = $h->handle($fields);

        $this->assertSame(200, $first->statusCode);
        $this->assertSame(200, $second->statusCode);
        $this->assertSame('100.000000000', $this->fetchBalance('chat42'));
        $this->assertSame(1, $this->telegramCalls, 'telegram must be hit at most once per order');
    }

    public function testBadSignatureRejected(): void
    {
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => 'chat42:order7',
            'status' => '1',
        ]);
        $fields['amount'] = '999999'; // tampered after signing
        $this->seed('chat42', 'order7', userBalance: '0');

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame('0', $this->fetchBalance('chat42'));
        $this->assertSame(0, $this->fetchStatus('chat42', 'order7'));
    }

    public function testFailedStatusIsAckedWithoutSideEffects(): void
    {
        $this->seed('chat42', 'order7', userBalance: '0');
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => 'chat42:order7',
            'status' => '2', // failed
        ]);

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('0', $this->fetchBalance('chat42'));
        $this->assertSame(0, $this->fetchStatus('chat42', 'order7'));
    }

    public function testTestFlagSkipsCrediting(): void
    {
        $this->seed('chat42', 'order7', userBalance: '0');
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => 'chat42:order7',
            'status' => '1',
            'test' => '1',
        ]);

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('0', $this->fetchBalance('chat42'));
        $this->assertSame(0, $this->fetchStatus('chat42', 'order7'));
    }

    public function testMalformedOrderIdReturns400(): void
    {
        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => "1' OR '1'='1:abc",
            'status' => '1',
        ]);

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(400, $response->statusCode);
    }

    public function testMissingUserRow500s(): void
    {
        // Paylink exists, but user row does not — the handler must surface this.
        $this->pdo->prepare(
            'INSERT INTO paylinks (rowid, chatid, status) VALUES (:r, :c, 0)',
        )->execute([':r' => 'order7', ':c' => 'ghost']);

        $fields = $this->signedPayload([
            'shop_id' => 'shop1',
            'amount' => '100',
            'order_id' => 'ghost:order7',
            'status' => '1',
        ]);

        $response = $this->handler('1.0')->handle($fields);

        $this->assertSame(500, $response->statusCode);
    }

    // ---- helpers --------------------------------------------------------

    private function handler(string $rate): Handler
    {
        $telegram = new TelegramClient('test-bot-token', function () {
            $this->telegramCalls++;
            return ['status' => 200, 'body' => '{"ok":true}'];
        });
        $rates = new class ($rate) implements TokenRateProvider {
            public function __construct(private readonly string $rate)
            {
            }
            public function currentRate(): string
            {
                return $this->rate;
            }
        };
        return new Handler(
            verifier:  new WebhookVerifier(self::SECRET),
            paylinks:  new PaylinkRepository($this->pdo),
            users:     new UserRepository($this->pdo),
            telegram:  $telegram,
            rates:     $rates,
            config:    new Config(
                telegramBotToken: 'test',
                tegroShopId:      'shop1',
                tegroApiKey:      'api',
                tegroSecretKey:   self::SECRET,
                tokenSymbol:      'TKN',
                tokenCurrency:    'RUB',
                markupPercent:    110.0,
                dbDsn:            'sqlite::memory:',
                dbUser:           'u',
                dbPassword:       '',
                logPath:          '/tmp/test.log',
            ),
            logger:    new NullLogger(),
        );
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    private function signedPayload(array $fields): array
    {
        $copy = $fields;
        ksort($copy);
        $sign = strtolower(md5(http_build_query($copy, '', '&', PHP_QUERY_RFC1738) . self::SECRET));
        $fields['sign'] = $sign;
        return $fields;
    }

    private function seed(string $chatId, string $rowId, string $userBalance): void
    {
        $this->pdo->prepare(
            'INSERT INTO paylinks (rowid, chatid, status) VALUES (:r, :c, 0)',
        )->execute([':r' => $rowId, ':c' => $chatId]);
        $this->pdo->prepare(
            'INSERT INTO users (chatid, balance) VALUES (:c, :b)',
        )->execute([':c' => $chatId, ':b' => $userBalance]);
    }

    private function fetchBalance(string $chatId): string
    {
        $stmt = $this->pdo->prepare('SELECT balance FROM users WHERE chatid = :c');
        $stmt->execute([':c' => $chatId]);
        /** @var string|false $row */
        $row = $stmt->fetchColumn();
        return $row === false ? '' : (string) $row;
    }

    private function fetchStatus(string $chatId, string $rowId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT status FROM paylinks WHERE rowid = :r AND chatid = :c',
        );
        $stmt->execute([':r' => $rowId, ':c' => $chatId]);
        /** @var int|false $row */
        $row = $stmt->fetchColumn();
        return $row === false ? -1 : (int) $row;
    }
}
