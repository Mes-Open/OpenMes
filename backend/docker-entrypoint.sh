#!/bin/sh
set -e

# Copy .env.example to .env if .env doesn't exist
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Generate APP_KEY if not set
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
fi

# Run any pending migrations automatically
echo "Running database migrations..."
php artisan migrate --force

# Start Laravel server
exec "$@"
