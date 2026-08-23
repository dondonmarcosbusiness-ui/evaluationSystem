#!/bin/sh
set -e

# Clear bootstrap cache
rm -rf bootstrap/cache/*.php
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*.php
rm -rf storage/framework/sessions/*

# Use array cache during migration to avoid cache table dependency
CACHE_STORE=array php artisan config:clear
CACHE_STORE=array php artisan cache:clear

# Fresh migration (clean corrupted DB)
CACHE_STORE=array php artisan migrate:fresh --force

# Now cache config with real driver
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
