FROM dunglas/frankenphp:1.4-php8.3-bookworm

ARG APP_ENV=development
ARG ENV_FILE

# Environment variables
ENV APP_ENV=${APP_ENV}
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV FRANKENPHP_DOCUMENT_ROOT=/var/www/html/public
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# Install system dependencies
RUN apt-get update && \
    apt-get install -y \
    git \
    curl \
    wget \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    libssh2-1-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo \
    pdo_sqlite \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Conditionally install Xdebug for development
RUN if [ "$APP_ENV" = "development" ]; then \
    install-php-extensions xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/xdebug.ini; \
    fi

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs

# Configure environment specific settings
RUN if [ "$APP_ENV" = "development" ]; then \
    export PHP_OPCACHE_VALIDATE_TIMESTAMPS=1; \
    fi

WORKDIR /var/www/html

# Copy environment file
COPY ${ENV_FILE} .env

# Copy application files
COPY . .

# Install Turso PHP extension
ENV COMPOSER_HOME=/root/.composer
RUN composer global require darkterminal/turso-php-installer
ENV PATH="/root/.composer/vendor/bin:${PATH}"
RUN turso-php-installer install -n --thread-safe

# Verify extensions
RUN php -m | grep libsql && \
    php -m | grep pdo_sqlite

# Install dependencies
RUN if [ "$APP_ENV" = "production" ]; then \
    composer install --optimize-autoloader --no-dev --no-interaction \
    && npm ci --omit=dev \
    && npm run build; \
    else \
    composer install --optimize-autoloader --no-interaction \
    && npm install; \
    fi

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Health check
HEALTHCHECK --interval=30s --timeout=3s CMD curl -f http://localhost:8000/up || exit 1

# Entrypoint script
RUN mv .docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000 9003 5173

ENTRYPOINT ["docker-entrypoint.sh"]
