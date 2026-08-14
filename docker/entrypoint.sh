#!/bin/sh
set -e

# Ensure Laravel can write where it needs to
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Storage symlink (harmless if it already exists)
php artisan storage:link --force >/dev/null 2>&1 || true

# Make the built assets available to the nginx container via the shared volume
if [ -d /var/www/html/public ]; then
    cp -a /var/www/html/public/. /webroot/
fi

exec "$@"
