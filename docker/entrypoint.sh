#!/bin/bash
set -e

cd /var/www

# Write runtime environment variables into .env
# Coolify injects env vars into Docker environment — write them to .env
# so Laravel can read them (artisan commands need .env file)
printenv | grep -E "^(APP_|DB_|REDIS_|MAIL_|REVERB_|BROADCAST_|QUEUE_|SESSION_|CACHE_|AWS_|FILESYSTEM_|FRONTEND_|CLOUDINARY_)" | while IFS='=' read -r key value; do
    if [ -n "$value" ]; then
        if grep -q "^${key}=" .env 2>/dev/null; then
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    fi
done

# Clear and rebuild config cache with fresh env values
APP_KEY_VAL=$(grep "^APP_KEY=" .env 2>/dev/null | cut -d'=' -f2-)
if [ -n "$APP_KEY_VAL" ]; then
    php artisan config:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "WARNING: APP_KEY not found in .env, skipping cache"
fi

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf