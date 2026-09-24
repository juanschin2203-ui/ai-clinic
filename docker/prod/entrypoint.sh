#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Rocket Coding — container entrypoint
#
# A single image serves three production roles. The role is selected at
# container start via the command argument (CMD):
#
#   docker run rocket-coding:latest web       # nginx + php-fpm
#   docker run rocket-coding:latest worker    # horizon (queue workers)
#   docker run rocket-coding:latest cron      # Laravel schedule:work
#
# Kubernetes / ECS task definitions set different commands for each Deployment.
# ----------------------------------------------------------------------------
set -euo pipefail

ROLE="${1:-web}"

# Common startup: ensure storage is writable, cache config, warm routes.
if [ "${APP_ENV:-production}" != "local" ]; then
    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan event:cache --no-interaction || true
fi

case "$ROLE" in
    web)
        exec /usr/bin/supervisord -c /etc/supervisord.conf
        ;;
    worker)
        # Horizon supervises the queue workers itself; container = single process.
        exec php /var/www/html/artisan horizon --no-interaction
        ;;
    cron)
        # schedule:work runs the Laravel scheduler in a loop (replaces the
        # need for a cron entry). Single-process container.
        exec php /var/www/html/artisan schedule:work --no-interaction
        ;;
    migrate)
        # One-shot migration container for CI/CD deploys. Exits 0 on success.
        exec php /var/www/html/artisan migrate --force --no-interaction
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: $ROLE (expected: web|worker|cron|migrate)"
        exit 2
        ;;
esac
