# Notifications & alert channels

NetPulse sends an **alert** when a connection crosses into an unhealthy state (a run of consecutive
failed or threshold-breaching measurements) and a **recovery** when it comes back. Delivery fans out
to whichever generic channels you enable — **email**, **chat** (Slack / Telegram / Discord / …) and
**webhook**.

Everything here is configured from the UI at **Settings → Notifications** — no restart, no editing
env files. Saved values are stored in the database (secrets encrypted at rest) and **override** the
`NOTIFY_*` / `MAILER_DSN` environment defaults documented in [Configuration](./configuration).

::: tip Where to click
Sign in, then go to **Settings → Notifications**. Configure a channel, **Save**, then **Send test** to
verify it before you rely on it.
:::

## Turn it on

- **Send notifications** — the master switch. Off by default.
- **Alert after N consecutive failures** — the debounce window (default **3**). An alert fires the
  moment a connection has N consecutive unhealthy measurements; a recovery fires when a healthy
  result follows such a run. This prevents a single blip from paging you.

Then enable one or more channels below.

## Email

| Field | What to enter |
| --- | --- |
| **Recipients** | Comma-separated addresses, e.g. `ops@example.com, oncall@example.com`. |
| **Mailer DSN** | A [Symfony Mailer DSN](https://symfony.com/doc/current/mailer.html#transport-setup) — usually SMTP. |

A typical SMTP DSN:

```txt
smtp://user:password@smtp.example.com:587
```

The DSN is a **write-only secret** (stored encrypted, never shown back). Leave it blank to keep the
existing one.

## Chat (Slack / Telegram / Discord / …)

The chat channel is generic: it takes a **Symfony Notifier chatter DSN**, so any provider with a
notifier bridge works. NetPulse ships the Slack, Telegram and Discord bridges.

| Provider | DSN format |
| --- | --- |
| **Slack** | `slack://BOT-TOKEN@default?channel=CHANNEL` |
| **Telegram** | `telegram://BOT-TOKEN@default?channel=CHAT_ID` |
| **Discord** | `discord://default?webhook_id=ID&token=TOKEN` |

How to get the credentials:

- **Slack** — create a Slack app, add the `chat:write` scope, install it to the workspace, and use
  the **Bot User OAuth Token** (`xoxb-…`). `CHANNEL` is the target channel name or ID. See the
  [Slack notifier docs](https://symfony.com/doc/current/notifier.html#slack).
- **Telegram** — create a bot via [@BotFather](https://t.me/botfather) for the `BOT-TOKEN`, then use
  the numeric chat id of the destination as `CHAT_ID`.
- **Discord** — create a channel **Webhook** in Discord; its URL contains the `webhook_id` and
  `token`.

The DSN is a **write-only secret** (encrypted at rest). Leave it blank to keep the existing one.

::: info More providers
Any [Symfony Notifier chatter](https://symfony.com/doc/current/notifier.html#notifier-chatter) works
if its bridge is installed (`composer require symfony/<provider>-notifier`). The DSN field accepts any
of them.
:::

## Webhook

Enter a **URL** and NetPulse `POST`s a JSON body to it on every alert/recovery:

```json
{
  "kind": "alert",
  "severity": "critical",
  "subject": "…",
  "body": "…",
  "context": { "probe": "…", "connection": "…", "reason": "…", "downloadBits": 0, "pingMs": 0 },
  "timestamp": "2026-06-08T12:00:00+00:00"
}
```

The URL is a **write-only secret** (it can embed a token), encrypted at rest. A non-2xx response is
treated as a failed delivery (logged, with the URL redacted).

## Send test

The **Send test** button dispatches a sample alert through the **currently saved** enabled channels
and reports a per-channel result (`sent` / `skipped` / `failed`). Save first, then test. It never
touches the metrics counter.

## Secrets & encryption

The mailer/chat DSN and the webhook URL are encrypted at rest with `SETTINGS_ENCRYPTION_KEY` (falling
back to `TOTP_ENCRYPTION_KEY`). Without a key set, channel secrets can't be saved — the page shows a
clear warning. Generate one with:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

## Resilience

- A channel that's enabled but **not configured** is skipped with a logged warning — never a crash.
- If one channel **fails**, the others still deliver; the failure is logged (with secrets redacted)
  and counted on `netpulse_notifications_sent_total{status="failed"}`.

## Digest

Separately from alerts, NetPulse can send a periodic **digest** of every connection's health. It is
not self-scheduled — run it from the host cron (it honours the same enabled channels):

```bash
docker compose exec -T app php bin/console app:notifications:digest --period=daily
```

## Environment fallback

If you prefer config-as-code, the same settings have ENV fallbacks (used until an admin saves a value
in the UI): `NOTIFY_ENABLED`, `NOTIFY_CONSECUTIVE_THRESHOLD`, `NOTIFY_CHANNELS`, `NOTIFY_EMAIL_TO`,
`MAILER_DSN`, `NOTIFY_CHAT_DSN`, `NOTIFY_WEBHOOK_URL` — see [Configuration](./configuration#notifications).
