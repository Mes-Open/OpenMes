FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    linux-headers \
    nodejs \
    npm

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    zip \
    mbstring \
    opcache \
    gd \
    pcntl \
    sockets

# PHP upload / memory limits
COPY backend/docker-php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY backend/ .
COPY modules/ ./modules/
# Workspace UI package — backend/package.json depends on it via `file:../packages/ui`,
# which resolves to /var/www/packages/ui relative to this WORKDIR. Must be present
# before `npm ci` or the `@openmes/ui` import fails to resolve during `npm run build`.
COPY packages/ /var/www/packages/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# The RoadRunner binary for Laravel Octane (concurrent app server), taken from
# the official image rather than downloaded during the build.
#
# `vendor/bin/rr get-binary` asks api.github.com for the release list, which
# makes every build depend on GitHub being reachable and on DNS answering at
# that moment. Five services in docker-compose.yml build from this Dockerfile,
# so a single `up --build` made that call several times over in parallel — enough
# for a home router's resolver to return NODATA and fail the install outright.
#
# The version is pinned to match `spiral/roadrunner` in composer.lock. Bump both
# together: Octane loads the PHP side from vendor and executes this binary, and
# they are meant to be the same release.
COPY --from=ghcr.io/roadrunner-server/roadrunner:2025.1.14 /usr/bin/rr /var/www/html/rr
RUN chmod +x /var/www/html/rr

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Clean up node_modules + the build-only workspace sources to reduce image size
RUN rm -rf node_modules /var/www/packages

# Copy entrypoint script
COPY backend/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 8000

# Set entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Serve with Laravel Octane on RoadRunner — a concurrent, in-memory app server
# (keeps the framework booted between requests; handles many simultaneous
# requests via a worker pool). Replaces the single-threaded `artisan serve`,
# which serialized requests and froze under any held connection.
#
# `--workers=auto` scales to CPU count; tune with --workers / --max-requests.
# Caveat: Octane keeps the app in memory across requests, so app/package state
# that assumes a fresh boot per request can leak. Validate the full app under
# Octane before production; to fall back, override CMD to:
#   php artisan serve --host=0.0.0.0 --port=8000   (dev only, single-threaded)
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
