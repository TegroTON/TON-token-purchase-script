<?php

declare(strict_types=1);

namespace Tegro\Purchase\Telegram;

/**
 * Minimal Telegram Bot API client — only sendMessage. Network access goes
 * through a closure so tests can inject a transport without monkey-patching
 * curl. The default transport posts JSON to the official endpoint.
 *
 * @phpstan-type Transport \Closure(string $url, array<string, mixed> $payload): array{status:int, body:string}
 */
final readonly class TelegramClient
{
    /** @var Transport */
    private \Closure $transport;

    /**
     * @param Transport|null $transport
     */
    public function __construct(
        private string $botToken,
        ?\Closure $transport = null,
    ) {
        if ($botToken === '') {
            throw new \InvalidArgumentException('botToken must not be empty');
        }
        $this->transport = $transport ?? self::defaultCurlTransport(...);
    }

    public function sendMessage(string $chatId, string $text): void
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        ($this->transport)($url, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int, body:string}
     */
    private static function defaultCurlTransport(string $url, array $payload): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("telegram_transport: {$err}");
        }
        /** @var int $status */
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($body) ? $body : ''];
    }
}
