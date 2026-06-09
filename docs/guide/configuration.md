# Configuration

NetPulse is configured almost entirely through **environment variables**. The
repository ships a `.env.dist` that documents every supported variable with its
default — copy it to `.env` and edit the values you care about:

```bash
cp .env.dist .env
```

Some **general** and **SSO** settings can also be edited from the dashboard under
**Settings**. When an administrator saves those in the UI they are persisted to the
`app_settings` table and **take precedence** over the matching environment variable,
which then only acts as a fallback. The tables below note where that applies.

::: info
All `php`/`composer`/console commands run **inside the `app` Docker container**. The
one-off form is `docker compose exec -T app <cmd>`.
:::

## Core

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_ENV` | `dev` | Symfony environment (`dev`, `prod`, `test`). |
| `APP_SECRET` | — | Symfony secret used for signing/CSRF. Set a unique random value. |
| `DATABASE_URL` | `sqlite:///%kernel.project_dir%/var/netpulse.sqlite` | Database DSN. SQLite for dev/test; a PostgreSQL DSN for production. |
| `MESSENGER_TRANSPORT_DSN` | `sync://` | Messenger transport for command/query buses. |
| `NETPULSE_VERSION` | — | Version label surfaced in the UI and metrics. |

::: tip MESSENGER_TRANSPORT_DSN
The default `sync://` runs message handlers **inline** (in-process), so no worker is
needed. Only switch to a real async transport (e.g. Doctrine/AMQP) if you also run a
`messenger:consume` worker to drain it.
:::

## Metrics & observability

| Variable | Default | Purpose |
| --- | --- | --- |
| `PROMETHEUS_METRICS_ENABLED` | `true` | Enables the `/metrics` endpoint. |
| `PROMETHEUS_ALLOWED_IPS` | _(empty)_ | Empty = allow all. Set a CSV of IPs/CIDRs to restrict who may scrape `/metrics`. |
| `MEASUREMENT_FRESHNESS_WINDOW` | `3600` | Seconds a measurement is considered "fresh" for freshness/staleness metrics. |

The `docker-compose` stack bundles **Prometheus** and **Grafana** services (Grafana is
provisioned with a dashboard), and Prometheus scrapes `/metrics` out of the box. An
optional **VictoriaMetrics** service is available under the `vm` compose profile.

If you'd rather push metrics to a remote store, enable Prometheus `remote_write`:

| Variable | Default | Purpose |
| --- | --- | --- |
| `REMOTE_WRITE_ENABLED` | `false` | Enable Prometheus `remote_write`. |
| `REMOTE_WRITE_URL` | — | Target `remote_write` endpoint. |
| `REMOTE_WRITE_AUTH` | — | Authorization for the remote endpoint. |
| `REMOTE_WRITE_EXTRA_LABELS` | — | Extra labels appended to remote-written series. |

## Probe agent

These variables are consumed by the **agent** process (`app:agent:run`), which polls the
server and runs the Ookla Speedtest CLI.

| Variable | Default | Purpose |
| --- | --- | --- |
| `NETPULSE_API_URL` | — | Base URL the agent calls (e.g. `http://app:8080`). |
| `PROBE_ID` | — | UUID of the probe this agent represents. |
| `PROBE_TOKEN` | — | Per-probe Bearer token from `app:probe:create`. |
| `OOKLA_BINARY` | `speedtest` | Path/name of the Ookla Speedtest CLI in the image. |
| `AGENT_POLL_INTERVAL` | `60` | Seconds between polls for due work. |

::: tip
These are read **only by the agent process** — the server image leaves them blank.
`AGENT_POLL_INTERVAL` is the fallback **poll cadence** (how often the agent checks for
work), **not** the test frequency. How often a connection is actually tested is set by
its **schedule** (`testsPerDay`/cron). See [How it works](./how-it-works) for the full
flow.
:::

## Notifications

::: tip Configure these in the UI
Alert channels are managed at **Settings → Notifications** (saved values override the ENV defaults
below, with secrets encrypted at rest). See the dedicated **[Notifications](./notifications)** guide
for per-channel setup and the Slack / Telegram / Discord DSN formats. The variables below are the
fallbacks used until an admin saves a value.
:::

| Variable | Default | Purpose |
| --- | --- | --- |
| `NOTIFY_ENABLED` | `false` | Master switch for alert/recovery/digest delivery. |
| `NOTIFY_CONSECUTIVE_THRESHOLD` | `3` | Consecutive bad measurements before an alert fires (edge-debounced). |
| `NOTIFY_CHANNELS` | — | CSV of channels: `email`, `chat`, `webhook`. |
| `NOTIFY_EMAIL_TO` | — | Recipient address for the `email` channel. |
| `MAILER_DSN` | `null://null` | Symfony Mailer DSN (the default sends nothing). |
| `NOTIFY_CHAT_DSN` | — | Symfony Notifier chatter DSN for the `chat` channel. |
| `NOTIFY_WEBHOOK_URL` | — | Target URL for the `webhook` channel. |

Alert and recovery notifications fire on **recorded measurements**, debounced by
`NOTIFY_CONSECUTIVE_THRESHOLD`. The periodic **digest is not self-scheduled** — run it
from the host cron:

```bash
docker compose exec -T app php bin/console app:notifications:digest --period=daily
```

A cron example (daily at 08:00, weekly digest Mondays at 08:05):

```txt
0 8 * * *   cd /opt/netpulse && docker compose exec -T app php bin/console app:notifications:digest --period=daily
5 8 * * 1   cd /opt/netpulse && docker compose exec -T app php bin/console app:notifications:digest --period=weekly
```

## Two-factor (TOTP)

| Variable | Default | Purpose |
| --- | --- | --- |
| `TOTP_ENCRYPTION_KEY` | — | 64 hex chars (32 bytes); encrypts stored TOTP secrets. |

Generate a key:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

This is required only to actually **enrol or use** TOTP 2FA (Settings → Security).

::: warning
Losing or rotating `TOTP_ENCRYPTION_KEY` **locks users out of TOTP** — existing secrets
can no longer be decrypted. Recovery codes remain the fallback in that case.
:::

## Single sign-on (OIDC)

| Variable | Default | Purpose |
| --- | --- | --- |
| `OIDC_NAME` | — | Display name for the SSO button. |
| `OIDC_CLIENT_ID` | — | Client ID registered at the identity provider. |
| `OIDC_CLIENT_SECRET` | — | Client secret (stored encrypted at rest — see below). |
| `OIDC_AUTHORIZATION_URL` | — | Provider authorization endpoint. |
| `OIDC_TOKEN_URL` | — | Provider token endpoint. |
| `OIDC_USERINFO_URL` | — | Provider userinfo endpoint. |
| `OIDC_SCOPES` | — | Requested scopes (e.g. `openid profile email`). |
| `OIDC_REDIRECT_URL` | — | The callback URL registered at the IdP. |

::: info
SSO is an **alternate login for an existing account**, matched by **verified email** —
it never auto-creates users. Register `<app-base-url>/login/oidc/callback` as the
redirect URI at your IdP. The authorization/token/userinfo endpoints can be read from
your provider's `<issuer>/.well-known/openid-configuration` document.
:::

## In-app settings & secret encryption

| Variable | Default | Purpose |
| --- | --- | --- |
| `NETPULSE_SITE_NAME` | — | Site name (ENV fallback; overridden by Settings in `app_settings`). |
| `NETPULSE_TIMEZONE` | — | Display timezone (ENV fallback; overridden by Settings). |
| `SETTINGS_ENCRYPTION_KEY` | _(falls back to `TOTP_ENCRYPTION_KEY`)_ | 64 hex chars; encrypts secret settings (e.g. the OIDC client secret) at rest. |

`NETPULSE_SITE_NAME` and `NETPULSE_TIMEZONE` are **fallbacks**: once an administrator
saves them in the dashboard Settings page they are written to `app_settings` and take
precedence over the environment values.

`SETTINGS_ENCRYPTION_KEY` encrypts secret settings (such as the OIDC client secret) so
they are not stored in plaintext. It is 64 hex chars and **falls back to
`TOTP_ENCRYPTION_KEY`** if unset. With **no key set at all**, secret settings cannot be
saved.

::: tip
Reuse the same generation command for either key:

```bash
php -r "echo bin2hex(random_bytes(32));"
```
:::

See [How it works](./how-it-works) for scheduling and the agent loop, and
[Getting started](./getting-started) for first-run setup.
