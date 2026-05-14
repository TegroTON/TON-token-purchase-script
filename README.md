# TON-token-purchase-script

[![CI](https://img.shields.io/github/actions/workflow/status/TegroTON/TON-token-purchase-script/ci.yml?branch=main&label=CI&logo=github)](https://github.com/TegroTON/TON-token-purchase-script/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%5E8.1-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Conventional Commits](https://img.shields.io/badge/Conventional%20Commits-1.0.0-fa6673)](https://www.conventionalcommits.org)
[![PHPStan level 8](https://img.shields.io/badge/PHPStan-level%208-2c3e50)](https://phpstan.org)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue)](https://www.php-fig.org/psr/psr-12/)

A production-ready PHP template for selling **any TON-network token** in a Telegram bot, using **[Tegro.Money](https://tegro.money/)** as the fiat acquirer.

Strict types, PDO prepared statements, MD5-signature webhook verification, idempotent crediting, full PHPUnit coverage of the security-critical paths. Drop your bot/token specifics into a thin layer, deploy.

> **Status:** verified against [`tegro.money/docs/en/`](https://tegro.money/docs/en/) on 2026-05-15.
> The package itself is generic — the token symbol, currency, and rate provider are all configurable.

---

## What this is

A complete, tested example of the **payment-receive side** of a Telegram-bot purchase flow:

1. The user clicks **Buy** in your bot. Your bot creates a paylink row (`paylinks` table) and asks Tegro.Money for a hosted payment URL.
2. The user pays on Tegro.Money's hosted page.
3. Tegro.Money POSTs a signed notification to `public/postback.php`.
4. This script:
   - **verifies the MD5 signature** against your `TEGRO_SECRET_KEY`,
   - **atomically claims the paylink** so duplicate retries are no-ops,
   - **credits the user's token balance** in a single DECIMAL-safe `UPDATE`,
   - **notifies the buyer** via Telegram.

The bot-side flow (handling `/buy`, creating paylinks, generating Tegro URLs) is intentionally **not** in this repo — it varies per project. This script handles only the part that absolutely must be correct under adversarial input.

## What is **not** in this repo

- Bot conversation logic (`/start`, `/buy`, button handlers).
- Tegro.Money order-creation client (use a separate HTTP integration).
- A rate provider — `TokenRateProvider` is an interface; wire it to a DEX, an oracle, or a hardcoded constant for tests.
- Front-end / admin panel.

## Install

```bash
git clone https://github.com/TegroTON/TON-token-purchase-script.git
cd TON-token-purchase-script
composer install
cp .env.example .env
# fill .env — see "Configuration" below
mysql -u $DB_USER -p $DB_NAME < migrations/001_initial.sql
```

Requirements:

- PHP **8.1+** with `ext-curl`, `ext-pdo`, `ext-pdo_mysql`, `ext-bcmath`, `ext-json`
- MySQL 8 or MariaDB 10.4+
- A Tegro.Money shop with `Shop ID`, `API key`, and `Secret key` from cabinet → Shop → Settings
- A Telegram bot token from [@BotFather](https://t.me/BotFather)

## Configuration

All secrets live in `.env`. Never commit it — the supplied `.gitignore` excludes it.

| Variable | What |
|---|---|
| `TELEGRAM_BOT_TOKEN` | From `@BotFather`. |
| `TEGRO_SHOP_ID` | Shop ID, visible in Tegro cabinet. |
| `TEGRO_API_KEY` | Used to sign outgoing requests (HMAC-SHA256). Not used by this repo directly but kept here for the order-creation side. |
| `TEGRO_SECRET_KEY` | Used to **verify** the incoming MD5 webhook signature. **This is the most security-sensitive value in the project.** |
| `TOKEN_SYMBOL` | Cosmetic — shown in the user's confirmation message ("100 X credited"). |
| `TOKEN_CURRENCY` | Fiat currency code: `RUB`, `USD`, `EUR`. Default `RUB`. |
| `MARKUP_PERCENT` | Pricing markup as a percentage of the upstream rate. `100` = no markup, `110` = +10%, etc. |
| `DB_DSN` / `DB_USER` / `DB_PASSWORD` | Standard PDO triple. The DSN must include `charset=utf8mb4`. |
| `LOG_PATH` | Where to append JSON log lines. Use an absolute path outside the web root. |

Then in your Tegro.Money cabinet:

> **Shop → Settings → Notification URL** → `https://yourdomain.tld/postback.php`

## Project layout

```
public/postback.php       Slim entrypoint — wires the container, hands off to Handler.
src/Config/Config.php     Immutable env-loaded config.
src/Signature/            WebhookVerifier + VerifiedNotification DTO.
src/Postback/             Handler + helpers (OrderIdParser, TokenAmountCalculator, TokenRateProvider iface).
src/Repository/           Paylinks + Users (single-statement, prepared, idempotent).
src/Telegram/             Minimal Bot API client (sendMessage).
src/Database/Database.php PDO factory with the safety flags pre-set.
src/Enum/PaymentStatus.php  0..4 enum.
src/Exception/            Domain exceptions.
migrations/001_initial.sql  Tables: users, paylinks (InnoDB, utf8mb4, FK).
tests/                    PHPUnit specs for verifier, parser, calculator, handler.
```

## Wiring `TokenRateProvider`

The shipped `public/postback.php` includes a stub that **throws on every call** — this is deliberate so a misconfigured deploy can't accidentally credit zero tokens. Replace it with something real:

```php
$rates = new class implements TokenRateProvider {
    public function currentRate(): string
    {
        // Return the current fiat-per-token rate as a decimal string.
        // Example: 1 token = 0.05 RUB → return '0.05'.
        return '0.05';
    }
};
```

For production, plug in your DEX, oracle, or off-chain price feed here. Pricing math is in BCMath, so the rate stays a string end-to-end — no float drift.

## Security model

**The webhook endpoint is the perimeter.** Anyone on the public internet can POST to `/postback.php`. The script defends with three layers:

1. **MD5 signature with your `SECRET_KEY`.** Unsigned or tampered payloads return `403 forbidden` before touching the database. See `src/Signature/WebhookVerifier.php`.
2. **Atomic claim.** The transition `status: 0 → 1` happens in a single conditional `UPDATE` statement (`PaylinkRepository::claimPaid`). Duplicate or replayed notifications get a `200 OK` no-op.
3. **Prepared statements everywhere.** `order_id` is parsed through a strict allowlist regex (`^-?[A-Za-z0-9_-]{1,64}$`) before it reaches any query. No interpolation.

Run the test suite to see the boundary conditions exercised:

```bash
composer test
```

For vulnerability reports, **do not open public issues**. Use [GitHub Private Vulnerability Reporting](https://github.com/TegroTON/TON-token-purchase-script/security/advisories/new). Full policy in [SECURITY.md](SECURITY.md).

## Development

```bash
composer install
composer phpstan       # static analysis at level 8 + strict rules
composer phpcs         # PSR-12 style check
composer test          # PHPUnit
composer check         # all of the above
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full contribution flow.

## License

MIT — see [LICENSE](LICENSE). Take it, ship your bot, send a star if it helped.

## Changelog

Versioned semver-style. See [Releases](https://github.com/TegroTON/TON-token-purchase-script/releases) for tagged versions.
