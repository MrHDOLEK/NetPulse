# Contributing

Thanks for helping improve NetPulse — this page covers setting up a dev environment, the quality gate every change must pass, and how to open a pull request. For the big picture, read the [architecture overview](./guide/architecture); to get the stack running, start with [getting started](./guide/getting-started).

## Local development

NetPulse runs in Docker. Bring the stack up once and develop against it:

```bash
make install   # build the image, start the stack, run composer install
make start     # docker compose up -d (start an already-built stack)
make stop      # docker compose down --remove-orphans
make bash      # open a shell inside the app container
make help      # list all make targets
```

Every `php`, `composer`, and `bin/console` command runs **inside the `app` container** — never on the host. Use a shell (`make bash`) or the one-off form:

```bash
docker compose exec -T app composer install
docker compose exec -T app composer migrate     # build/upgrade the DEV database from migrations
docker compose exec -T app php bin/console app:dev:seed
```

After changing a Twig template or a Tailwind design token, rebuild the CSS:

```bash
docker compose exec -T app php bin/console tailwind:build
```

::: info No Node for the app
The frontend is built with Symfony **AssetMapper** — JS is served from the import map and there is no `npm`/Vite/Webpack step. The only `tailwind:build` is the CSS rebuild above.
:::

## The quality gate

Before you push, this checklist must be green. CI runs the same job — **Test&lint PHP codebase** — on every push and pull request to `main`, so getting it green locally means getting it green in CI.

```bash
docker compose exec -T app composer cs:fix       # code style (CI uses cs:check, a dry-run)
docker compose exec -T app composer phpstan      # PHPStan level 10, zero errors
docker compose exec -T app vendor/bin/deptrac --no-progress   # architecture, 0 violations
docker compose exec -T app composer db:test      # apply migrations to the TEST database
docker compose exec -T app composer test         # PHPUnit
docker compose exec -T app vendor/bin/behat      # BDD
```

::: tip Run them sequentially, inside the container
Run the gate steps one at a time in the `app` container (e.g. via `make bash`). Don't background or overlap container commands.
:::

## Adding a module

Modules live under `src/<Module>/` and each splits into `Domain`, `Application`, and `Infrastructure`.

1. Create `src/<Module>/{Domain,Application,Infrastructure}/`.
2. Write the **Domain interfaces first** — entities, value objects, repository interfaces — with no framework imports.
3. Add the use-cases under `Application/Command/<UseCase>/` and any read models under `Application/ReadModel/`.
4. Add the HTTP `*Action`(s) under `Application/{Action,Api}/` and import them in `config/routes.php` (web pages with no prefix; agent endpoints under the `/api` prefix).
5. Wire Doctrine + Symfony in `Infrastructure/` and register services in `config/services.php`.
6. Add the new layer(s) to `deptrac.yaml` and keep deptrac at **0 violations**.

## Pull requests

- Branch off `main`.
- Keep the [quality gate](#the-quality-gate) green — CI will reject anything that isn't.
- **PR titles must start with `- `** (a dash and a space), optionally preceded by an issue reference — e.g. `- Add per-connection alert thresholds` or `#42 - Fix the ping unit on the heatmap`. A CI check enforces the pattern `^(#\d+ )?- .+`.
- Fill in the PR template, and keep each change focused on a single concern.
- Reporting a bug or proposing a feature? Use the repository's issue templates so we capture the details we need.

::: warning Keep the diff reviewable
Large, mixed-concern PRs are hard to review and slow to land. Split unrelated changes into separate branches and PRs.
:::

## Documentation

This site lives in `docs/` and is built with **VitePress**. Documentation tooling *does* use Node — run it from the `docs/` directory:

```bash
npm install        # once, in docs/
npm run docs:dev   # local preview with hot reload
npm run docs:build # production build
```

Diagrams are authored as **Mermaid** fenced code blocks. Edit the relevant page, preview it with `docs:dev`, and include doc updates in the same PR as the code they describe.
