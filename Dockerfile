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

# Clear bootstrap cache and regenerate package discovery
RUN rm -rf bootstrap/cache/*.php && \
    composer dump-autoload --optimize --no-dev --no-interaction && \
    php artisan package:discover --ansi || true

# Build frontend assets
RUN npm run build

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

