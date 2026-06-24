#!/bin/sh
set -e

# Several services share this image (backend/Octane, reverb, queue, mqtt,
# modbus). Only the PRIMARY app server (Octane) may run migrations/seeders —
# otherwise every sidecar races the same migrate against the same database on a
# fresh `docker compose up`, producing "duplicate table / column already exists"
# errors. Detect the primary by its command.
case "$*" in
    *octane:start*) IS_PRIMARY=1 ;;
    *) IS_PRIMARY=0 ;;
esac

# ── .env ────────────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    echo "[OpenMES] Creating .env from .env.example..."
    cp .env.example .env
fi

# ── Sync Docker Compose env vars into .env ──────────────────────────────────
# Docker Compose passes the real values via environment variables, but
# config:cache reads from the .env file.  Sync important vars so the
# cached config uses the correct credentials.
for VAR in APP_ENV APP_DEBUG APP_URL QUEUE_CONNECTION DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD BROADCAST_CONNECTION REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET REVERB_HOST REVERB_PORT REVERB_SCHEME REVERB_SERVER_HOST REVERB_SERVER_PORT; do
    eval VAL=\$$VAR
    if [ -n "$VAL" ]; then
        if grep -q "^${VAR}=" .env; then
            sed -i "s|^${VAR}=.*|${VAR}=${VAL}|" .env
        else
            echo "${VAR}=${VAL}" >> .env
        fi
    fi
done

if ! grep -q "APP_KEY=base64:" .env; then
    echo "[OpenMES] Generating APP_KEY..."
    NEW_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=$NEW_KEY|" .env
    else
        echo "APP_KEY=$NEW_KEY" >> .env
    fi
    echo "[OpenMES] APP_KEY set successfully."
fi

# ── Clear stale bootstrap cache ─────────────────────────────────────────────
# bootstrap/cache is a persisted volume, so on an upgrade (git pull + rebuild)
# the OLD compiled config/route caches survive into the NEW image. Wipe them by
# file (no DB/artisan needed yet) before anything reads them — including the
# route cache, so a stale route map can't keep serving the previous release's
# middleware (e.g. the old role:Admin /admin group instead of tab.access).
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
      bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/route.php
php artisan package:discover --ansi 2>/dev/null || true

if [ "$IS_PRIMARY" = "1" ]; then
    # ── Migrations ───────────────────────────────────────────────────────────
    echo "[OpenMES] Running migrations..."
    php artisan migrate --force

    # ── Seeders (idempotent) ─────────────────────────────────────────────────
    echo "[OpenMES] Running seeders..."
    php artisan db:seed --class=RolesAndPermissionsSeeder --force
    php artisan db:seed --class=IssueTypesSeeder --force
    php artisan db:seed --class=LineStatusSeeder --force

    # Reset the Spatie permission cache so the freshly-seeded roles/permissions
    # are authoritative. Without this an upgrade can keep serving a stale cached
    # map (the cache store lives in the persisted DB), which surfaces as bogus
    # 403 "user does not have the right roles" even for the admin.
    php artisan permission:cache-reset 2>/dev/null || true

    # ── Default admin (only if no users exist) ───────────────────────────────
    # Export so the tinker subprocess can read them with getenv() — values are
    # NOT interpolated into the PHP string (a password with quotes/backslashes
    # would otherwise break the --execute snippet), and the password is never
    # echoed to the logs.
    export ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
    export ADMIN_EMAIL="${ADMIN_EMAIL:-admin@openmmes.local}"
    export ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin1234!}"

    USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n1 | tr -d '[:space:]')

    if [ "$USER_COUNT" = "0" ]; then
        echo "[OpenMES] Creating admin account (username: ${ADMIN_USERNAME})..."
        php artisan tinker --execute="
            \$u = \App\Models\User::create([
                'name'                  => 'Administrator',
                'username'              => getenv('ADMIN_USERNAME'),
                'email'                 => getenv('ADMIN_EMAIL'),
                'password'              => bcrypt(getenv('ADMIN_PASSWORD')),
                'force_password_change' => false,
                'email_verified_at'     => now(),
            ]);
            \$u->assignRole('Admin');
        "
        echo ""
        echo "╔══════════════════════════════════════════╗"
        echo "║            OpenMES — admin               ║"
        echo "║                                          ║"
        echo "║  URL:      ${APP_URL:-http://localhost}"
        echo "║  Login:    ${ADMIN_USERNAME}"
        echo "║  Password: (the ADMIN_PASSWORD you configured)"
        echo "║                                          ║"
        echo "╚══════════════════════════════════════════╝"
        echo ""
    else
        echo "[OpenMES] Admin already exists, skipping default user creation."
    fi

    # ── Mark as installed (skip web installer) ───────────────────────────────
    if [ ! -f storage/installed ]; then
        echo "[OpenMES] Marking application as installed..."
        date '+%Y-%m-%d %H:%M:%S' > storage/installed
    fi
else
    echo "[OpenMES] Sidecar container ($*) — skipping migrations/seeders (handled by the primary)."
fi

# ── Cache ────────────────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[OpenMES] Ready at http://localhost:8080"

# ── Scheduler (runs every 60s in background) ─────────────────────────────────
# Only on the primary, so sidecars don't each spawn a competing scheduler.
if [ "$IS_PRIMARY" = "1" ]; then
    (while true; do php artisan schedule:run >> storage/logs/scheduler.log 2>&1; sleep 60; done) &
fi

# ── Queue worker (background) ────────────────────────────────────────────────
# QUEUE_CONNECTION defaults to "database" (see docker-compose.yml), so real jobs
# (webhook delivery, CSV import, MQTT, auto-update) need a worker — without one
# they'd queue and never run. Run a self-restarting worker on the primary so a
# default `docker compose up` processes jobs out of the box (the standalone
# `queue-worker` service, profile: workers, can still scale this out).
if [ "$IS_PRIMARY" = "1" ]; then
    (while true; do php artisan queue:work --sleep=1 --tries=5 --max-time=3600 >> storage/logs/queue-worker.log 2>&1; sleep 2; done) &
fi

exec "$@"
