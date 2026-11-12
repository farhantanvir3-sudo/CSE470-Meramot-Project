#!/usr/bin/env bash
set -e

cd /var/www/meramot/backend

# Configure Apache to listen on Render's dynamic $PORT (defaults to 8000)
PORT="${PORT:-8000}"
sed -ri "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Ensure runtime cache and storage directories exist
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs storage/app/public bootstrap/cache
php artisan storage:link --force >/dev/null 2>&1 || true

# Generate APP_KEY fallback if not set in environment
if [ -z "${APP_KEY}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

# Materialize runtime .env file so Apache mod_php workers always see Render env vars
cat > /var/www/meramot/backend/.env <<EOF
APP_NAME="${APP_NAME:-Meramot}"
APP_ENV="${APP_ENV:-production}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_KEY="${APP_KEY}"
APP_URL="${APP_URL:-https://cse470-meramot-project.onrender.com}"
LOG_CHANNEL="${LOG_CHANNEL}"
DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_SSLMODE="${DB_SSLMODE:-require}"
DATABASE_URL="${DATABASE_URL}"
DATABASE_URL_UNPOOLED="${DATABASE_URL_UNPOOLED}"
CACHE_DRIVER="${CACHE_DRIVER:-file}"
SESSION_DRIVER="${SESSION_DRIVER:-file}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
FILESYSTEM_DISK="${FILESYSTEM_DISK:-local}"
AWS_BUCKET="${AWS_BUCKET:-docs-imgs}"
AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID}"
AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY}"
AWS_ENDPOINT_URL_S3="${AWS_ENDPOINT_URL_S3}"
AWS_REGION="${AWS_REGION:-ap-southeast-1}"
ENABLE_EMAIL_NOTIFICATIONS="${ENABLE_EMAIL_NOTIFICATIONS:-true}"
GMAIL_USER="${GMAIL_USER:-meramotbd.official@gmail.com}"
GMAIL_FROM="${GMAIL_FROM:-Meramot Official <meramotbd.official@gmail.com>}"
GMAIL_CLIENT_ID="${GMAIL_CLIENT_ID}"
GMAIL_CLIENT_SECRET="${GMAIL_CLIENT_SECRET}"
GMAIL_REFRESH_TOKEN="${GMAIL_REFRESH_TOKEN}"
GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID:-$GMAIL_CLIENT_ID}"
GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET:-$GMAIL_CLIENT_SECRET}"
GOOGLE_REDIRECT_URI="${GOOGLE_REDIRECT_URI:-https://cse470-meramot-project.onrender.com/auth/google/callback}"
BULKSMS_API_KEY="${BULKSMS_API_KEY}"
BULKSMS_SENDER_ID="${BULKSMS_SENDER_ID:-8809648908088}"
EOF

php artisan config:clear

# Run migrations (using direct unpooled URL when available) and seed if DB is empty
if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    echo "Running PostgreSQL schema migrations on Neon..."
    DATABASE_URL="${DATABASE_URL_UNPOOLED:-$DATABASE_URL}" php artisan migrate --force

    USER_COUNT=$(php -r '
        require "vendor/autoload.php";
        $app = require_once "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        echo \App\Models\User::count();
    ' 2>/dev/null || echo "0")

    if [ "${USER_COUNT}" = "0" ] || [ "${FORCE_SEED:-false}" = "true" ]; then
        echo "Seeding Meramot marketplace data into Neon PostgreSQL..."
        DATABASE_URL="${DATABASE_URL_UNPOOLED:-$DATABASE_URL}" php artisan db:seed --force
    else
        RIDER_RENEWED_COUNT=$(php -r '
            require "vendor/autoload.php";
            $app = require_once "bootstrap/app.php";
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            echo \App\Models\DeliveryAgent::where("company_email", "rahim.uddin@meramotbd.com")->count();
        ' 2>/dev/null || echo "0")

        if [ "${RIDER_RENEWED_COUNT}" = "0" ]; then
            echo "Syncing renewed Delivery Partner company credentials & seeded workspace trips..."
            DATABASE_URL="${DATABASE_URL_UNPOOLED:-$DATABASE_URL}" php artisan db:seed --class=Database\\Seeders\\UserSeeder --force
            DATABASE_URL="${DATABASE_URL_UNPOOLED:-$DATABASE_URL}" php artisan db:seed --class=Database\\Seeders\\SprintOneDemoSeeder --force
        fi
    fi
fi

# Cache configuration & Blade views, then fix ownership for Apache (www-data)
php artisan config:cache
php artisan view:cache

chown -R www-data:www-data /var/www/meramot/backend/storage /var/www/meramot/backend/bootstrap/cache /var/www/meramot/backend/.env
chmod -R 775 /var/www/meramot/backend/storage /var/www/meramot/backend/bootstrap/cache

echo "Starting Meramot Apache/PHP 8.2 server on port ${PORT}..."
exec apache2-foreground
