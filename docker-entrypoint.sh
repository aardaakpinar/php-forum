#!/bin/sh
set -e

# uploads dizininin varligini ve yazma izinlerini garantiye al
mkdir -p /var/www/html/assets/uploads
chown -R www-data:www-data /var/www/html/assets/uploads
chmod 775 /var/www/html/assets/uploads

exec "$@"
