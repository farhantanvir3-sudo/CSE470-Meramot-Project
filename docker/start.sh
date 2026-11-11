#!/usr/bin/env bash
set -e

cd /var/www/meramot/backend

# Configure Apache to listen on Render's dynamic $PORT (defaults to 8000)
PORT="${PORT:-8000}"
sed -ri "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Ensure runtime cache and storage directories exist and are writable
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Generate APP_KEY fallback if not set in environment
if [ -z "${APP_KEY}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

php artisan config:clear

# Run migrations and seed automatically on first boot (when users table is empty or FORCE_SEED=true)
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    echo "Running PostgreSQL schema migrations on Neon..."
    php artisan migrate --force

    USER_COUNT=$(php -r '
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo \App\Models\User::count();
    ' 2>/dev/null || echo "0")

    if [ "${USER_COUNT}" = "0" ] || [ "${FORCE_SEED:-false}" = "true" ]; then
        echo "Seeding Meramot marketplace data into Neon PostgreSQL..."
        php artisan db:seed --force
    fi
fi

php artisan view:cache

echo "Starting Meramot Apache/PHP 8.2 server on port ${PORT}..."
exec apache2-foreground
