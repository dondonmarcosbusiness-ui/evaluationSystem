#!/bin/sh
set -e

# Clear any cached config that references missing tables
php artisan config:clear
php artisan cache:clear

# Fresh migration (drops all tables, re-creates)
php artisan migrate:fresh --force --seed

# Now cache config (safe because tables exist)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start server
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
