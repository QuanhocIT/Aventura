#!/bin/sh

set -e
cd /var/www/html

php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan migrate --force
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec /usr/bin/supervisord -c /etc/supervisord.conf
