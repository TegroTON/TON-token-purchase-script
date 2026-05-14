<?php

/**
 * Tegro.Money webhook entrypoint.
 *
 * Route in your web server:   POST /postback.php
 * Set this URL in your Tegro.Money cabinet → Shops → Settings → Notification URL.
 *
 * The script never trusts its input — every safety check is in the verifier
 * and the repository layer. The only job here is wiring.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Tegro\Purchase\Config\Config;
use Tegro\Purchase\Database\Database;
use Tegro\Purchase\Postback\Handler;
use Tegro\Purchase\Postback\TokenRateProvider;
use Tegro\Purchase\Repository\PaylinkRepository;
use Tegro\Purchase\Repository\UserRepository;
use Tegro\Purchase\Signature\WebhookVerifier;
use Tegro\Purchase\Telegram\TelegramClient;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
$config = Config::fromEnv();

$pdo = Database::connect($config->dbDsn, $config->dbUser, $config->dbPassword);

$logger = new class ($config->logPath) extends Psr\Log\AbstractLogger {
    public function __construct(private readonly string $path)
    {
    }
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $line = json_encode(
            [
                'ts' => date('c'),
                'level' => is_string($level) ? $level : 'info',
                'msg' => (string) $message,
                'ctx' => $context,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ) . PHP_EOL;
        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
};

/**
 * Wire a real rate provider before deploying — this stub fails fast so a
 * misconfigured deploy can't accidentally credit zero tokens to anyone.
 */
$rates = new class implements TokenRateProvider {
    public function currentRate(): string
    {
        throw new \RuntimeException(
            'TokenRateProvider is not configured. ' .
            'Provide an implementation that returns the current fiat→token rate ' .
            'as a positive decimal string (e.g. "0.05" for 1 token = 0.05 RUB).',
        );
    }
};

$handler = new Handler(
    verifier:  new WebhookVerifier($config->tegroSecretKey),
    paylinks:  new PaylinkRepository($pdo),
    users:     new UserRepository($pdo),
    telegram:  new TelegramClient($config->telegramBotToken),
    rates:     $rates,
    config:    $config,
    logger:    $logger,
);

/** @var array<string, scalar|null> $rawFields */
$rawFields = $_POST;
$response = $handler->handle($rawFields);

http_response_code($response->statusCode);
header('Content-Type: text/plain; charset=utf-8');
echo $response->body;
