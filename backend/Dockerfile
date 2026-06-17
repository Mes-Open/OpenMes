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

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Fetch the RoadRunner binary for Laravel Octane (concurrent app server).
# Placed in the project root — where Octane's RoadRunner server discovers it.
RUN ./vendor/bin/rr get-binary --location /var/www/html \
    && chmod +x /var/www/html/rr

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Clean up node_modules to reduce image size
RUN rm -rf node_modules

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
