#!/usr/bin/env bash
# render-build.sh - Build script for Render deployment

# Exit on error
set -e

# Print commands before executing
set -x

# Install system dependencies
apt-get update
apt-get install -y ffmpeg

# PHP & Composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install additional packages
composer require sentry/sentry-laravel cloudinary/cloudinary_php

# Install Sentry for error monitoring
composer require sentry/sentry-laravel

# Cleanup any previous cached data
php artisan clear-compiled
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Prepare the database
php artisan migrate --force

# Optimize for production
php artisan optimize

# Link storage for public access
php artisan storage:link

# Create health check endpoint
mkdir -p public/api
echo '{"status":"ok","timestamp":"'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}' > public/api/health

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Generate Swagger documentation
composer require darkaonline/l5-swagger --no-interaction
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider" --tag=config
php artisan l5-swagger:generate

echo "Build completed successfully!" 