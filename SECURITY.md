# Security Policy

Thank you for taking the time to look into the security of this project. Reports are treated as a first-class contribution.

## Supported versions

| Version | Status |
| ------- | ------ |
| `main`  | ✅ Active — security fixes land here. |
| Older tags | ❌ Not supported — please upgrade. |

## Reporting a vulnerability

**Do not open public GitHub issues for security problems.**

Use **GitHub Private Vulnerability Reporting** instead:

1. Go to the [Security tab](https://github.com/TegroTON/TON-token-purchase-script/security/advisories/new).
2. Click **Report a vulnerability**.
3. Include reproduction steps and a proposed severity.

The report stays private between you and the maintainers until a coordinated fix is published.

## In scope

This repository ships server-side PHP that verifies a signed webhook and credits a database row. The threat model is:

- A network attacker forging or replaying webhook POSTs.
- An attacker tampering with payload fields after signing.
- An attacker controlling fields that flow into SQL, logging, or Telegram messages.
- Time-of-check / time-of-use races on paylink processing.

Examples of what we want to hear about:

- Bypasses of the MD5 signature check or the constant-time comparison.
- Conditions under which a paylink can be claimed twice (double-credit).
- Any SQL execution path that is not prepared / parameterized.
- Injection into log files or Telegram messages.
- Dependency vulnerabilities reachable from the published code.

## Out of scope

- Vulnerabilities in the upstream payment processor itself — report those to the operator directly.
- Weaknesses of MD5 in the abstract: the upstream signature scheme requires MD5; we cannot unilaterally change it. We will, however, ship a stronger scheme as soon as the upstream supports one.
- DoS via extremely large payloads — apply request-body limits at your HTTP layer (`client_max_body_size`, etc.).
- Misconfiguration of your local `.env` or database — those are deploy concerns, not library bugs.

## Response SLA

Best-effort, no contractual guarantee:

| Event | Target |
| --- | --- |
| Acknowledge receipt | within 72 hours |
| Triage decision | within 7 days |
| Fix / mitigation for High or Critical | within 30 days |
| Public advisory + patched release | coordinated with reporter |

## Coordinated disclosure

After a fix ships:

1. A GitHub Security Advisory is published with a CVE where applicable.
2. The reporter is credited (unless they prefer otherwise).
3. The changelog references the advisory.

If you have a public-disclosure deadline (talk, blog post), tell us up front and we will work to meet it.
