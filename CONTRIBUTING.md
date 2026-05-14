# Contributing

Thanks for your interest in improving this project. The codebase is intentionally small — under 1000 lines of PHP — and the contribution bar reflects that: the goal is **stay minimal, stay obviously correct**.

## TL;DR

```bash
git clone https://github.com/TegroTON/TON-token-purchase-script.git
cd TON-token-purchase-script
composer install
composer check
```

If `composer check` passes on a fresh clone, your dev environment works.

## Prerequisites

- PHP **8.2, 8.3, or 8.4** — the CI matrix runs all three.
- Required extensions: `ext-curl`, `ext-pdo`, `ext-pdo_mysql`, `ext-bcmath`, `ext-json`.
- Composer 2.x.

## Project layout

```
src/        Library code — no I/O, no globals. Every dependency injected.
tests/      PHPUnit specs, one file per logical unit.
public/     Web entrypoints (postback.php). Wires the container.
migrations/ Plain .sql files, run manually or via your migration tool.
```

## What we accept

- **Bug fixes** with a failing test that demonstrates the bug.
- **Security hardening** — new tests around the verifier, parser, or repositories.
- **Documentation** that matches the upstream Tegro.Money docs or PHP behavior.
- **Test additions** that cover untouched branches.
- **CI / tooling** improvements that don't add complexity.

## What we don't accept

- **New runtime dependencies** unless absolutely unavoidable. The package depends on `psr/log` (a tiny interface package) and `vlucas/phpdotenv` — that's the bar.
- **Custom abstractions on top of Tegro.Money** (retries, queues, "smart" caching). Consumers can wrap the handler themselves.
- **Logic that "improves" the MD5 signature scheme.** It is fixed by the upstream API; any change breaks compatibility.
- **Breaking API changes** without a major-version bump.

## Development workflow

1. **Open an issue** describing the change (skip for trivial typos).
2. **Branch from `main`** with a descriptive name: `fix/replay-window`, `feat/refund-handler`.
3. **Write the test first** for bug fixes.
4. **Run the full check locally**:

   ```bash
   composer install
   composer check   # phpstan + phpcs + phpunit
   ```

5. **Open the PR**. Fill in the template. Link the issue.

## Commit message style

We use [Conventional Commits](https://www.conventionalcommits.org/):

| Prefix | When |
| --- | --- |
| `feat:` | User-visible feature |
| `fix:` | User-visible bug fix |
| `docs:` | README / SECURITY / etc. |
| `refactor:` | Code change with no behavioral effect |
| `test:` | Tests only |
| `chore:` | Build / lockfile / housekeeping |
| `ci:` | GitHub Actions / workflows |
| `deps:` | Dependency bumps (usually from Dependabot) |

Scope is optional and useful: `feat(handler): support refund webhooks`.

## Coding conventions

- **`declare(strict_types=1);`** at the top of every PHP file. No exceptions.
- **`final readonly class`** for value objects and services without state.
- **Constructor property promotion** for DTOs.
- **PSR-12** for style — enforced by `phpcs`.
- **PHPStan level 8** + strict-rules — enforced by `phpstan`.
- **No `mixed`** in exported signatures unless absolutely necessary, and then with a phpdoc-narrowed type.
- **Constant-time comparison** for any signature check (`hash_equals`).
- **Prepared statements** for every SQL query — no exceptions.

## Tests

- We use [PHPUnit 10](https://phpunit.de).
- New public methods need: happy path, malformed input, tampered input where applicable.
- Database tests use **in-memory SQLite** — fast, no external dependency. Production uses MySQL/MariaDB; the abstractions ensure both work.
- `composer test` must stay green on PHP 8.2, 8.3, 8.4.

## Reporting security issues

**Don't open public issues for security problems.** See [SECURITY.md](SECURITY.md).

## Code of conduct

Be patient, be specific, and assume good faith. Code reviewers may ask the same question twice — payment code deserves more scrutiny, not less.
