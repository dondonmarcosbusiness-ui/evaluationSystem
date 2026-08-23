#!/bin/sh
set -e

# Clear ALL caches before any artisan command
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*.php
rm -rf storage/framework/sessions/*

php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Fresh migration - wipe corrupted DB
php artisan migrate:fresh --force

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
