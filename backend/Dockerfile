# MoroRide backend — PHP 8.3 runtime (matches production target: Laravel 11 / PHP 8.3)
FROM php:8.3-cli

# System libraries needed to build the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions:
#   pdo_mysql  -> MySQL / MariaDB (shared-hosting + mororide.com production DB)
#   pdo_pgsql  -> PostgreSQL (local dev DB)
#   pdo_sqlite -> sqlite :memory: used by the test suite
#   bcmath/zip -> Laravel + composer common deps
#   pcntl      -> graceful Reverb/queue-worker signal handling
#   redis      -> cache/queue driver
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite bcmath zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# OPcache — critical on the slow Windows bind-mount: caches compiled bytecode in
# memory so each request does NOT re-read/re-compile the whole framework.
# enable_cli=1 is required because the app runs under the CLI SAPI (php -S).
RUN docker-php-ext-install opcache
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=30000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'realpath_cache_size=4096k'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/zz-mororide-opcache.ini

# Composer 2 from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

# Default: raw PHP built-in server via router (keeps container env overrides).
# `docker compose run` overrides this for artisan/composer.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "server.php"]
