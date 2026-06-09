# Getting started

Stand up NetPulse with Docker, create the first admin, wire a probe to a connection, and run the agent — start to first measurement.

::: info Prerequisites
- **Docker** and **Docker Compose**
- **git**
- ~1 GB free disk
- Free local ports: **8080** (dashboard), **9090** (Prometheus), **3000** (Grafana)
:::

## Install

Clone the repository and bring the stack up:

```bash
git clone https://github.com/MrHDOLEK/NetPulse.git && cd NetPulse
make install   # builds the image, starts containers, runs composer install
make start     # docker compose up -d
```

`make install` does the heavy lifting once; `make start` is the day-to-day "bring it up" command. Run `make help` to list every available target.

::: tip Platform notes
- On Linux the Makefile uses `docker compose`; on macOS it uses `docker-compose`. The Makefile **auto-detects** which is present, so the same `make` commands work either way.
- If you hit permission errors talking to the Docker daemon, prefix the command with `sudo`.
:::

## Build the database

NetPulse is **migrations-only** — never run `doctrine:schema:update`. Apply the migrations to build each database.

```bash
# DEV database
docker compose exec -T app composer migrate

# TEST database (only needed if you run the test suite)
docker compose exec -T app composer db:test
```

::: warning SQLite volume ownership
If you use the named `app_sqlite` volume, fix its ownership **once** so the web user can write the SQLite file:

```bash
docker compose exec -T app chown -R www-data:www-data var
```
:::

## Create the first admin

NetPulse is single-tenant. Until an admin account exists, **every page redirects to `/setup`**.

1. Open [http://localhost:8080](http://localhost:8080).
2. You land on `/setup`. Enter an **email** and a **password** (minimum **12 characters**).
3. After setup, sign in at [http://localhost:8080/login](http://localhost:8080/login).

::: details CLI alternative
You can create the admin from the console instead:

```bash
docker compose exec -T app php bin/console app:user:create --email=ops@example.com
```

Prefer the **hidden interactive prompt** — omit `--password` and let the command ask for it.
:::

## Add a probe and a connection

A **probe** is the machine that runs speed tests; a **connection** is the link you measure on a schedule.

**1. Create a probe** and capture its **id** and **one-time token** (the token is shown **once**):

```bash
docker compose exec -T app php bin/console app:probe:create "Office probe"
```

**2. Create a connection** bound to that probe:

```bash
docker compose exec -T app php bin/console app:connection:create "WAN" \
  --probe=<PROBE_ID> \
  --schedule-mode=even \
  --tests-per-day=288 \
  --server-pool=11111,22222
```

A connection with **no prior measurement is due immediately**, so the agent will pick it up on its first poll.

::: tip
Connections can also be created and managed from the **dashboard UI** — the CLI is handy for scripting and first-run setup.
:::

## Run the agent

The agent ships as a dedicated `agent` Docker service behind the **`agent` compose profile**, so it does **not** start with a plain `docker compose up`. Pass the probe identity via environment variables and start it under the profile:

```bash
PROBE_ID=<PROBE_ID> PROBE_TOKEN=<PROBE_TOKEN> \
  docker compose --profile agent up agent
```

The agent loop:

1. Polls `GET /api/v1/probes/<id>/due` (authenticated with the probe **Bearer token**).
2. Runs the Ookla `speedtest` CLI for each due task.
3. **POSTs** each result back to the server, where it surfaces on `/metrics` and the dashboard.

Run a **single tick** (handy for testing or ops):

```bash
docker compose --profile agent run --rm \
  -e PROBE_ID=<PROBE_ID> -e PROBE_TOKEN=<PROBE_TOKEN> \
  agent php bin/console app:agent:run --once
```

Verify the Ookla CLI is present in the image:

```bash
docker compose exec -T app speedtest --version
```

## See your data

Once results start landing:

- **Dashboard** — [http://localhost:8080](http://localhost:8080): Speed / Ping / Loss over **24h / 7d / 30d / 90d**, full **history at `/history`**, and a **heatmap at `/heatmap`**.
- **REST API docs** — [http://localhost:8080/api/doc](http://localhost:8080/api/doc) (Swagger via NelmioApiDoc).
- **Prometheus** — the `/metrics` endpoint, scraped by the bundled Prometheus + Grafana services.

::: tip Early-history charts
New tests cluster into a single point on a wide window at first. The chart shows individual points until enough history accumulates to spread out — keep the agent running and the curves fill in.
:::

## Run the prebuilt image (GHCR)

Don't want to build from source? The production image — **nginx + php-fpm in one container** — is
published to the **GitHub Container Registry** on every push to `main`. Pull and run it:

```bash
# a stable random secret (rotating it logs everyone out — keep it)
export APP_SECRET=$(openssl rand -hex 16)

docker run -d --name netpulse \
  -p 8080:8080 \
  -e APP_SECRET="$APP_SECRET" \
  -v netpulse_var:/var/www/var \
  ghcr.io/mrhdolek/netpulse:latest
```

…or with the bundled compose file:

```bash
docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
```

The container applies migrations on boot and serves the dashboard on
[http://localhost:8080](http://localhost:8080) — create the first admin at `/setup`, then add a probe
and connection as above. Available tags: `latest` (from `main`), `sha-<short>`, and `vX.Y.Z` for
tagged releases. Storage defaults to SQLite in the `var` volume; set `DATABASE_URL` to a PostgreSQL
DSN for larger installs.

## Grafana dashboard

The Docker Compose stack ships **Grafana**, provisioned against the Prometheus that scrapes
`/metrics`, with a ready-made **NetPulse Overview** dashboard. With the stack running, open
**[http://localhost:3000](http://localhost:3000)** (default login `admin` / `admin`) — the dashboard
is already there under *Dashboards*.

The dashboard JSON lives at
[`.docker/grafana/dashboards/netpulse-overview.json`](https://github.com/MrHDOLEK/NetPulse/blob/main/.docker/grafana/dashboards/netpulse-overview.json)
— import it into any Grafana that can read the NetPulse Prometheus metrics.

## Next

- [Configuration](./configuration) — environment variables and options.
- [How it works](./how-it-works) — scheduling, the agent loop, and the metrics path explained.
