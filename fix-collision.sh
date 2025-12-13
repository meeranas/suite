#!/bin/bash

# Quick fix script to remove Collision from packages.php cache

echo "🔧 Fixing Collision service provider issue..."

# Remove the problematic cache file
docker-compose -f docker-compose.prod.yml exec app rm -f bootstrap/cache/packages.php

# Regenerate package discovery (this will skip dev dependencies)
docker-compose -f docker-compose.prod.yml exec app php artisan package:discover --ansi

# If that still fails, manually edit the file
docker-compose -f docker-compose.prod.yml exec app sh -c "
    if [ -f bootstrap/cache/packages.php ]; then
        sed -i '/nunomaduro\/collision/,/},/d' bootstrap/cache/packages.php
        sed -i '/spatie\/laravel-ignition/,/},/d' bootstrap/cache/packages.php
        sed -i '/laravel\/sail/,/},/d' bootstrap/cache/packages.php
    fi
"

# Clear and rebuild caches
docker-compose -f docker-compose.prod.yml exec app php artisan config:clear
docker-compose -f docker-compose.prod.yml exec app php artisan route:clear
docker-compose -f docker-compose.prod.yml exec app php artisan view:clear

# Rebuild caches
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache

echo "✅ Fix applied! Try accessing the application now."

