FROM php:8.3.36-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    oniguruma-dev \
    linux-headers \
    nodejs \
    npm

# Configure GD extension. WebP too: uploads of image/webp are accepted and
# re-encoded through GD, which without it has no imagewebp() to do that with.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

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

# ionCube Loader — required to run modules distributed as encoded PHP.
#
# Inert without encoded files: the extension loads, costs a couple of MB, and
# does nothing until something actually asks it to run an encoded file. It is
# here rather than in a separate image because a module is installed into a
# running OpenMES through the admin panel, so the stock image has to be able to
# run whatever a licensed module ships — otherwise installing one would mean
# first swapping the whole installation for a different image.
#
# The base image is Alpine, so this is the musl build. ionCube publishes no
# musl loader for arm64; on that platform the step below is skipped and encoded
# modules will not run, while everything else is unaffected.
RUN set -eux; \
    arch="$(apk --print-arch)"; \
    if [ "$arch" = "x86_64" ]; then \
        curl -fsSL -o /tmp/ioncube.tar.gz \
            https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin-musl_x86-64.tar.gz; \
        tar -xzf /tmp/ioncube.tar.gz -C /tmp; \
        # The image exposes PHP_VERSION but not its major/minor parts, so the
        # loader's name is derived here rather than assumed.
        abi="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"; \
        cp "/tmp/ioncube/ioncube_loader_lin-musl_${abi}.so" \
            "$(php -r 'echo ini_get("extension_dir");')/"; \
        # Loaded as a zend_extension and prefixed 00- so it comes before the
        # other ini files: ionCube must be in place before anything tries to
        # include an encoded file.
        echo "zend_extension=ioncube_loader_lin-musl_${abi}.so" \
            > /usr/local/etc/php/conf.d/00-ioncube.ini; \
        rm -rf /tmp/ioncube /tmp/ioncube.tar.gz; \
        php -v | grep -q 'ionCube'; \
    else \
        echo "ionCube: no musl loader for $arch, skipping"; \
    fi

# Fail the build, not an upload, if GD came out without a format the
# application accepts. The test suite runs on the CI runner's PHP rather than
# in this image, so nothing else would notice.
RUN php -r 'foreach (["imagejpeg", "imagepng", "imagewebp"] as $f) { if (! function_exists($f)) { fwrite(STDERR, "GD is missing $f()\n"); exit(1); } }'

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

# Install PHP dependencies.
# A prebuilt production vendor tree is shipped with this deployment. When it
# is present, skip composer: the build host cannot reach codeload.github.com,
# and composer would otherwise fail after the code layer already succeeded.
RUN if [ ! -f vendor/autoload.php ]; then composer install --no-dev --optimize-autoloader --no-interaction; fi

# The RoadRunner binary for Laravel Octane is shipped in the build context
# instead of being pulled from ghcr.io: this build host cannot resolve ghcr.io,
# and the binary only needs to match the spiral/roadrunner version in
# composer.lock. Bump backend/rr together with that dependency.
COPY backend/rr /var/www/html/rr
RUN chmod +x /var/www/html/rr

# Install Node dependencies and build assets.
# This deployment ships prebuilt assets in backend/public/build. When they are
# present, skip npm entirely: `npm ci` inside the Alpine builder has been
# observed to abort with npm's own "Exit handler never called" bug, which
# would otherwise fail the image build after the backend code already built.
RUN if [ ! -f public/build/manifest.json ]; then npm ci && npm run build; fi

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
