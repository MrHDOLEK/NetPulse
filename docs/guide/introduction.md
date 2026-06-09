# Introduction

**NetPulse** is a self-hosted, metrics-first internet speed tracker built on PHP 8.4 and Symfony 7.3. It runs the official Ookla Speedtest CLI on a schedule, records every result, and turns those measurements into history, alerts, and first-class Prometheus metrics. Instead of opening a web page once in a while and trusting a single number, you get a continuous, queryable record of how your ISP actually performs over time — so you can spot degradation, prove an SLA breach, and back up a support ticket with data you own.

## Why NetPulse

- **Self-hosted — own your data.** Runs on your own hardware via Docker; measurements live in your database (SQLite for dev, PostgreSQL for production).
- **Metrics-first.** A native Prometheus `/metrics` endpoint plus a provisioned Grafana dashboard ship out of the box — NetPulse is built to be observed, not just viewed.
- **Scheduled and adaptive testing.** Tests run on a fixed interval or cron expression, and the adaptive policy retests a degraded connection sooner.
- **Multi-connection.** Monitor several links independently, each with its own schedule, server pool, and health thresholds.
- **Dashboard, history, and heatmap.** A server-rendered admin UI shows current health, trends, and a heatmap of results.
- **No Node toolchain for the app.** The frontend is built with Symfony AssetMapper, Tailwind CSS, and Alpine.js — no npm, no Vite, no build step to run.

## Core concepts

| Concept | What it is |
| --- | --- |
| **Probe** | An agent identity registered on the server. Each probe has an ID and a Bearer token used to authenticate against the agent API. |
| **Agent** | The `app:agent:run` process. It polls the server for due work, runs the Ookla CLI, and pushes results back. |
| **Connection** | A monitored link. It carries a schedule, a server pool, and health thresholds, and accumulates measurements over time. |
| **Measurement** | One speedtest result — the download/upload/latency figures from a single Ookla run. |
| **Schedule** | How often a connection is tested: `even` (a fixed number of tests per day) or `cron` (governed by cron expressions). |
| **Server pool** | The set of Ookla servers a connection tests against, chosen round-robin on each run. |
| **Health** | A connection's status derived from its recent measurements: **Healthy**, **Degraded**, or **Down**. |

## How the pieces fit

You configure connections in the dashboard (or with the console commands): each one names a probe, a schedule, a server pool, and its thresholds. The **agent** process polls the server — `GET /api/v1/probes/{id}/due` — to ask what work is due right now. The server computes "due" on demand for that poll (there is no server-side cron); a connection is due on its first run, then again whenever its schedule says so. For each due task the agent runs the **Ookla Speedtest CLI** and `POST`s the result back. Those recorded measurements then power everything downstream: the dashboard's current health, the history view and heatmap, the Prometheus `/metrics` endpoint, and alert/recovery notifications.

The dashboard's **Run test** button doesn't run anything itself — it inserts a one-shot "due now" marker that the agent consumes on its next poll. Likewise, the schedule sets the test *frequency*, while `AGENT_POLL_INTERVAL` only controls how often the agent *checks* for work.

For the full mechanism — including the scheduling math, the round-robin server selection, and diagrams of the request flow — see [How it works](./how-it-works).

## Next steps

- [Getting started](./getting-started) — install and run NetPulse for the first time.
- [How it works](./how-it-works) — the mechanisms and flow, with diagrams.
- [Configuration](./configuration) — environment variables and in-app options.
- [Contributing](/contributing) — develop, test, and contribute.

::: tip
Ready to try it? Head to [Getting started](./getting-started) — `make install` builds the image, starts the stack, and runs `composer install`, then you create the first admin at `/setup`.
:::
