#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

# php-fpm workers run as www-data; the bind-mounted .env is owned by the
# host user and often ends up 600, which silently breaks env loading (dotenv
# swallows the read error, so Laravel falls back to APP_ENV=production /
# no APP_KEY) without any obvious error in php-fpm's own log.
chmod 644 .env

if [ ! -f vendor/autoload.php ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -d node_modules ]; then
    echo "Installing npm dependencies..."
    npm install
fi

if [ ! -f public/build/manifest.json ]; then
    echo "Building frontend assets..."
    npm run build
fi

if ! grep -q "^APP_KEY=base64" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Waiting for database at ${DB_HOST:-db}:${DB_PORT:-3306}..."
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', '${DB_PASSWORD:-root}');" 2>/dev/null; do
    sleep 2
done
echo "Database is up."

php artisan migrate --force

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

exec php-fpm
