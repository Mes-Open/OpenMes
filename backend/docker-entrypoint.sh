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
for VAR in APP_KEY APP_ENV APP_DEBUG APP_URL QUEUE_CONNECTION DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD BROADCAST_CONNECTION REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET REVERB_HOST REVERB_PORT REVERB_SCHEME REVERB_SERVER_HOST REVERB_SERVER_PORT; do
    eval VAL=\$$VAR
    if [ -n "$VAL" ]; then
        if grep -q "^${VAR}=" .env; then
            sed -i "s|^${VAR}=.*|${VAR}=${VAL}|" .env
        else
            echo "${VAR}=${VAL}" >> .env
        fi
    fi
done

# No APP_KEY from compose (an install older than the installers writing one, or
# a hand-made compose file): keep ONE key on the storage volume instead of making
# a new one in the image layer on every start. A fresh key per start logged
# everyone out and made every encrypted value (webhook secrets, integration
# credentials) unreadable after each upgrade. Sidecars may start together, so the
# file is created atomically and whoever loses the race reads the winner's key.
if [ -z "$APP_KEY" ]; then
    KEY_FILE=storage/app/.app_key
    if [ ! -s "$KEY_FILE" ]; then
        mkdir -p storage/app
        TMP_KEY="$KEY_FILE.$$"
        echo "base64:$(php -r 'echo base64_encode(random_bytes(32));')" > "$TMP_KEY"
        chmod 600 "$TMP_KEY"
        # ln fails when another container got there first — then keep theirs,
        # unless what is there is empty.
        if ! ln "$TMP_KEY" "$KEY_FILE" 2>/dev/null && [ ! -s "$KEY_FILE" ]; then
            mv -f "$TMP_KEY" "$KEY_FILE"
        fi
        rm -f "$TMP_KEY"
        echo "[OpenMES] No APP_KEY in the environment — generated one and kept it in $KEY_FILE."
        echo "[OpenMES] Put the same value as APP_KEY in the .env next to docker-compose.yml to share it with every service."
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    if grep -q "^APP_KEY=" .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
    else
        echo "APP_KEY=$APP_KEY" >> .env
    fi
    export APP_KEY
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

# ── Modules installed through the panel ─────────────────────────────────────
# modules/ and public/modules/ are part of the image layer, so a recreated
# container (any image upgrade) starts without the modules an administrator
# installed, while the database still lists them as enabled. Their archives
# are kept on the storage volume; put them back and republish the frontend of
# every enabled module. Every container needs the module code (queue jobs,
# listeners), so this is not limited to the primary. Never fatal.
php artisan modules:restore --publish || echo "[OpenMES] Module restore failed — continuing without it."

if [ "$IS_PRIMARY" = "1" ]; then
    # ── Migrations ───────────────────────────────────────────────────────────
    echo "[OpenMES] Running migrations..."
    php artisan migrate --force

    # ── Seeders (idempotent) ─────────────────────────────────────────────────
    # DatabaseSeeder, not a hand-picked subset: it already lists exactly the
    # reference data a usable install needs, and every child upserts, so running
    # it on each start is safe. Naming three of the eight here is what left a
    # fresh install with no scrap reasons (the operator's "report scrap" picker
    # was empty), no downtime reasons, no material types and no label templates.
    echo "[OpenMES] Running seeders..."
    php artisan db:seed --class=DatabaseSeeder --force

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

    FRESH_INSTALL=0
    if [ "$USER_COUNT" = "0" ]; then
        FRESH_INSTALL=1
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

    # ── Anonymous usage reports ──────────────────────────────────────────────
    # On by default for a new installation; OPENMES_TELEMETRY=false switches it
    # off. The web wizard records the same default (a pre-ticked box), but a
    # Docker install never reaches that wizard — the next block skips it — so
    # the choice is recorded here.
    #
    # Only while the application is not yet marked installed, so this can never
    # overwrite a choice an administrator later made in Settings -> System. And
    # the default is applied only to a database that had no users a moment ago:
    # an existing installation that lost its storage/installed flag keeps the
    # answer it already has, instead of being switched back on.
    if [ ! -f storage/installed ]; then
        case "$(printf '%s' "${OPENMES_TELEMETRY:-}" | tr '[:upper:]' '[:lower:]')" in
            1 | true | yes | on) export TELEMETRY_CHOICE=true ;;
            "")
                if [ "$FRESH_INSTALL" = "1" ]; then
                    export TELEMETRY_CHOICE=true
                else
                    export TELEMETRY_CHOICE=""
                fi ;;
            *)                   export TELEMETRY_CHOICE=false ;;
        esac
        if [ -n "${TELEMETRY_CHOICE}" ]; then
            echo "[OpenMES] Recording usage-report choice: ${TELEMETRY_CHOICE}"
            # Read through getenv rather than interpolated, like the admin block above.
            php artisan tinker --execute="
                \App\Support\TelemetrySettings::put(
                    \App\Support\TelemetrySettings::SETTING_KEY,
                    getenv('TELEMETRY_CHOICE') === 'true',
                );
            " || echo "[OpenMES] Could not record the usage-report choice; reporting stays off."
            if [ "${TELEMETRY_CHOICE}" = "true" ]; then
                echo "[OpenMES] Anonymous usage reports are ON. Switch them off in Settings -> System -> Usage reporting, or set OPENMES_TELEMETRY=false."
            fi
        fi
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
# `|| true` so a non-zero run can't trip `set -e` and kill the restart loop.
if [ "$IS_PRIMARY" = "1" ]; then
    (while true; do php artisan schedule:run >> storage/logs/scheduler.log 2>&1 || true; sleep 60; done) &
fi

# ── Queue worker (background) ────────────────────────────────────────────────
# QUEUE_CONNECTION defaults to "database" (see docker-compose.yml), so real jobs
# (webhook delivery, CSV import, MQTT, auto-update) need a worker — without one
# they'd queue and never run. Run a self-restarting worker on the primary so a
# default `docker compose up` processes jobs out of the box (the standalone
# `queue-worker` service, profile: workers, can still scale this out). `|| true`
# keeps `set -e` from killing the loop when `queue:work` exits non-zero.
if [ "$IS_PRIMARY" = "1" ]; then
    (while true; do php artisan queue:work --sleep=1 --tries=5 --max-time=3600 >> storage/logs/queue-worker.log 2>&1 || true; sleep 2; done) &
fi

exec "$@"
