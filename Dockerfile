# Multi-stage Dockerfile for Laravel application

# Stage 1: Build stage - Install dependencies and build assets
FROM php:8.2-fpm AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Copy artisan file (needed for composer post-install scripts)
COPY artisan ./

# Copy bootstrap directory (needed for artisan)
COPY bootstrap ./bootstrap

# Install PHP dependencies (skip scripts to avoid artisan dependency)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# Copy package files
COPY package.json package-lock.json ./

# Install Node dependencies
RUN npm ci

# Copy application files (exclude bootstrap cache to regenerate it)
COPY . .

# Clear bootstrap cache completely
RUN rm -rf bootstrap/cache/*.php

# Regenerate autoloader
RUN composer dump-autoload --optimize --no-dev --no-interaction

# Regenerate package discovery (this will skip dev dependencies due to composer.json config)
RUN php artisan package:discover --ansi 2>&1 || true

# Manually remove any dev-only packages from packages.php if they still exist
RUN php -r "\
    \$file = 'bootstrap/cache/packages.php'; \
    if (file_exists(\$file)) { \
        \$content = file_get_contents(\$file); \
        \$content = preg_replace('/\\'nunomaduro\\\\collision\\'.*?\\},\\s*/s', '', \$content); \
        \$content = preg_replace('/\\'spatie\\\\laravel-ignition\\'.*?\\},\\s*/s', '', \$content); \
        \$content = preg_replace('/\\'laravel\\\\sail\\'.*?\\},\\s*/s', '', \$content); \
        file_put_contents(\$file, \$content); \
    } \
"

# Build Vue.js frontend assets (for Laravel Inertia)
RUN npm run build

# Build React frontend (for AI Control Hub)
WORKDIR /var/www/html/resources/react
RUN npm ci && npm run build
WORKDIR /var/www/html

# Stage 2: Production stage
FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy built application from builder stage
COPY --from=builder /var/www/html /var/www/html

# Clear and regenerate bootstrap cache in production stage
RUN rm -rf bootstrap/cache/*.php && \
    php artisan package:discover --ansi || true

# Ensure APP_ENV is production (set in environment, but verify)
# Clear config cache to ensure production settings are used
RUN php artisan config:clear || true

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/sites-available/default

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

