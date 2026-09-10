#!/bin/sh
# Entrypoint for the siupknext PHP-FPM container.
#
# Responsibilities (in order):
#   1. Ensure storage / bootstrap directories exist (idempotent).
#   2. Run composer install if vendor/ is missing (first-boot).
#   3. Run `package:discover` if the package manifest is stale.
#   4. Hand off to CMD (php-fpm).
#
# Keep this script FAST — it runs on every container start. Long-running
# setup tasks belong in a separate init service (e.g. ollama-init).

set -eu

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

# First boot: install composer deps from the bind-mounted host tree.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ missing — running composer install"
    composer install --no-interaction --prefer-dist
fi

# Refresh Laravel's package manifest if composer.json changed since the
# last image build. Cheap (~50ms) and avoids stale provider lists.
if [ composer.json -nt bootstrap/cache/packages.php ] 2>/dev/null; then
    echo "[entrypoint] composer.json newer than cache — re-discovering packages"
    php artisan package:discover --ansi >/dev/null
fi

exec "$@"
