#!/usr/bin/env bash

set -euo pipefail

if [[ ! -f artisan || ! -f composer.json ]]; then
    echo "Run this script from the SafeLMS application directory."
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Missing .env. Copy .env.cpanel.example to .env and add the cPanel MySQL credentials."
    exit 1
fi

if grep -Eq '^DB_CONNECTION=(sqlite|)$' .env; then
    echo "The production .env must use DB_CONNECTION=mysql."
    exit 1
fi

composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\RolePermissionSeeder' --force
php artisan storage:link || true
php artisan optimize

echo
echo "SafeLMS is connected and migrated."
echo "Create the first administrator with:"
echo "php artisan safelms:create-super-admin admin@your-domain.example"
