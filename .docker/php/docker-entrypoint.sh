#!/bin/sh
set -e

mkdir -p var
chown -R www-data:www-data var 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] applying database migrations…"
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration \
        || echo "[entrypoint] WARNING: migrations did not complete; check DATABASE_URL"
fi

if [ ! -d var/cache/"${APP_ENV:-prod}" ]; then
    echo "[entrypoint] warming cache…"
    php bin/console cache:warmup || true
    chown -R www-data:www-data var 2>/dev/null || true
fi

exec "$@"
