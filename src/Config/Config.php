<?php

declare(strict_types=1);

namespace Tegro\Purchase\Config;

/**
 * Immutable runtime configuration loaded from environment variables.
 *
 * Construct via Config::fromEnv() at the entrypoint exactly once and pass
 * the instance down through constructor injection — never read getenv()
 * directly anywhere else.
 */
final readonly class Config
{
    public function __construct(
        public string $telegramBotToken,
        public string $tegroShopId,
        public string $tegroApiKey,
        public string $tegroSecretKey,
        public string $tokenSymbol,
        public string $tokenCurrency,
        public float $markupPercent,
        public string $dbDsn,
        public string $dbUser,
        public string $dbPassword,
        public string $logPath,
    ) {
        if ($this->markupPercent <= 0.0) {
            throw new \InvalidArgumentException('markupPercent must be > 0');
        }
        if ($this->tokenSymbol === '') {
            throw new \InvalidArgumentException('tokenSymbol must not be empty');
        }
    }

    public static function fromEnv(): self
    {
        return new self(
            telegramBotToken: self::requireString('TELEGRAM_BOT_TOKEN'),
            tegroShopId:      self::requireString('TEGRO_SHOP_ID'),
            tegroApiKey:      self::requireString('TEGRO_API_KEY'),
            tegroSecretKey:   self::requireString('TEGRO_SECRET_KEY'),
            tokenSymbol:      self::requireString('TOKEN_SYMBOL'),
            tokenCurrency:    self::stringOr('TOKEN_CURRENCY', 'RUB'),
            markupPercent:    self::floatOr('MARKUP_PERCENT', 100.0),
            dbDsn:            self::requireString('DB_DSN'),
            dbUser:           self::requireString('DB_USER'),
            dbPassword:       self::stringOr('DB_PASSWORD', ''),
            logPath:          self::stringOr('LOG_PATH', '/tmp/tegro-purchase.log'),
        );
    }

    private static function requireString(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || !is_string($value) || $value === '') {
            throw new \RuntimeException("Required environment variable {$key} is missing or empty");
        }
        return $value;
    }

    private static function stringOr(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || !is_string($value) || $value === '') {
            return $default;
        }
        return $value;
    }

    private static function floatOr(string $key, float $default): float
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || !is_string($value) || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new \RuntimeException("Environment variable {$key} must be numeric, got: {$value}");
        }
        return (float) $value;
    }
}
