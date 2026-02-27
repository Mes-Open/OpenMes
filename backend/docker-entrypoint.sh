#!/bin/sh
set -e

# ── .env ────────────────────────────────────────────────────────────────────
if [ ! -f .env ]; then
    echo "[OpenMES] Creating .env from .env.example..."
    cp .env.example .env
fi

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

# ── Migrations ───────────────────────────────────────────────────────────────
echo "[OpenMES] Running migrations..."
php artisan migrate --force

# ── Seeders (idempotent) ─────────────────────────────────────────────────────
echo "[OpenMES] Running seeders..."
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=IssueTypesSeeder --force
php artisan db:seed --class=LineStatusSeeder --force

# ── Default admin (only if no users exist) ───────────────────────────────────
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@openmmes.local}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-Admin1234!}"

USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -n1 | tr -d '[:space:]')

if [ "$USER_COUNT" = "0" ]; then
    echo "[OpenMES] Creating admin account (username: ${ADMIN_USERNAME})..."
    php artisan tinker --execute="
        \$u = \App\Models\User::create([
            'name'                  => 'Administrator',
            'username'              => '${ADMIN_USERNAME}',
            'email'                 => '${ADMIN_EMAIL}',
            'password'              => bcrypt('${ADMIN_PASSWORD}'),
            'force_password_change' => false,
        ]);
        \$u->assignRole('Admin');
    "
    echo ""
    echo "╔══════════════════════════════════════════╗"
    echo "║            OpenMES — admin               ║"
    echo "║                                          ║"
    echo "║  URL:      ${APP_URL:-http://localhost}"
    echo "║  Login:    ${ADMIN_USERNAME}"
    echo "║  Hasło:    ${ADMIN_PASSWORD}"
    echo "║                                          ║"
    echo "╚══════════════════════════════════════════╝"
    echo ""
else
    echo "[OpenMES] Admin already exists, skipping default user creation."
fi

# ── Mark as installed (skip web installer) ───────────────────────────────────
if [ ! -f storage/installed ]; then
    echo "[OpenMES] Marking application as installed..."
    date '+%Y-%m-%d %H:%M:%S' > storage/installed
fi

# ── Cache ────────────────────────────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[OpenMES] Ready at http://localhost:8080"

exec "$@"
