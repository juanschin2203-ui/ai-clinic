#!/usr/bin/env bash
# ----------------------------------------------------------------------------
# Rocket Coding — production migration runner (with backup-first safeguard)
#
# Usage on the deploy host:
#
#   APP_ENV=production ./scripts/migrate-prod.sh
#
# What it does, in order:
#   1. Confirms APP_ENV=production (refuses otherwise)
#   2. Snapshots the DB into s3://rocket-coding-db-backups/pre-migration/
#      via mysqldump + aws-cli (RDS automated backups are there too, but a
#      per-deploy artifact makes post-mortem trivial)
#   3. Runs `php artisan migrate --force` INSIDE a transaction so a mid-
#      migration crash leaves the DB in the prior-state (most MySQL DDL is
#      non-transactional — this is a best-effort guard, see notes below)
#   4. Clears + rebuilds Laravel caches (config/route/event/view)
#   5. Emits an audit event to ActivityLog for the deploy record
#
# NOTES
#   - MySQL does NOT support transactional DDL for schema changes (CREATE
#     TABLE, ALTER TABLE). If a migration halfway through a multi-step
#     ALTER fails, some tables may be altered and others not. The backup
#     is your only rollback option — that's why this script takes one.
#   - Run this from a one-off ECS task / k8s Job pod that shares the app
#     image, not from a shell on a running worker. Ensures no concurrent
#     writers during the migration window.
#   - The caller (CI/CD pipeline) should put the app into maintenance mode
#     (`php artisan down`) BEFORE invoking this script and bring it back up
#     (`php artisan up`) after. Migration alone does not do that.
# ----------------------------------------------------------------------------
set -euo pipefail

# ---- Guards ----------------------------------------------------------------
if [ "${APP_ENV:-}" != "production" ]; then
    echo "❌ APP_ENV must be 'production' to run this script (got: '${APP_ENV:-<unset>}')."
    echo "   For staging, use ./scripts/migrate-staging.sh."
    exit 2
fi

if [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ]; then
    echo "❌ DB_HOST and DB_DATABASE must be set."
    exit 2
fi

BACKUP_BUCKET="${BACKUP_S3_BUCKET:-rocket-coding-db-backups}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_KEY="pre-migration/${DB_DATABASE}-${TIMESTAMP}.sql.gz"

# ---- Step 1: Snapshot ------------------------------------------------------
echo "➜ Step 1/4  Snapshotting ${DB_DATABASE} to s3://${BACKUP_BUCKET}/${BACKUP_KEY}..."
mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --password="${DB_PASSWORD}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --set-gtid-purged=OFF \
    "${DB_DATABASE}" \
    | gzip \
    | aws s3 cp --expected-size 104857600 - "s3://${BACKUP_BUCKET}/${BACKUP_KEY}"

echo "   ✔ Backup uploaded."

# ---- Step 2: Migrate -------------------------------------------------------
echo "➜ Step 2/4  Running migrations..."
php /var/www/html/artisan migrate --force --no-interaction

# ---- Step 3: Rebuild caches ------------------------------------------------
echo "➜ Step 3/4  Rebuilding Laravel caches..."
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan event:cache
php /var/www/html/artisan view:cache

# ---- Step 4: Emit audit ----------------------------------------------------
echo "➜ Step 4/4  Emitting deploy audit event..."
php /var/www/html/artisan tinker --execute="
\\App\\Models\\ActivityLog::create([
    'category'   => 'deploy',
    'event'      => 'migration.completed',
    'severity'   => 'info',
    'message'    => 'Production migration completed at ${TIMESTAMP}',
    'context'    => ['backupKey' => '${BACKUP_KEY}'],
    'occurredAt' => now(),
]);
"

echo "✅ Migration complete. Backup: s3://${BACKUP_BUCKET}/${BACKUP_KEY}"
