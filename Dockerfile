FROM php:8.3-apache-trixie

LABEL org.opencontainers.image.title="PHP Forum System" \
      org.opencontainers.image.description="A lightweight, modern, and security-hardened forum built with PHP & MariaDB" \
      org.opencontainers.image.url="https://github.com/aardaakpinar/php-forum" \
      org.opencontainers.image.source="https://github.com/aardaakpinar/php-forum" \
      org.opencontainers.image.licenses="MIT"

# Sistem paketlerini güncelle (base image'dan sonra çıkan güvenlik yamaları),
# healthcheck için curl, pdo_mysql ve apache modüllerini kur.
# Derleme araçları (gcc, make vb.) yalnızca pdo_mysql derlemesi için gerekli;
# işi bitince kaldırılır. Bu hem imaj boyutunu hem de zafiyet sayısını düşürür.
RUN apt-get update \
    && apt-get upgrade -y --no-install-recommends \
    && apt-get install -y --no-install-recommends curl \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Uygulama kaynak kodlarını kopyala
WORKDIR /var/www/html
COPY . /var/www/html/

# Entrypoint scriptini doğrudan container içinde oluştur (CRLF ve dosya kopyalama hatalarını önler)
RUN { \
        echo '#!/bin/sh'; \
        echo 'set -e'; \
        echo 'mkdir -p /var/www/html/assets/uploads'; \
        echo 'chown -R www-data:www-data /var/www/html/assets/uploads'; \
        echo 'chmod 775 /var/www/html/assets/uploads'; \
        echo 'exec "$@"'; \
    } > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && mkdir -p /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

# Kalıcı kullanıcı dosyaları için volume tanımı
VOLUME ["/var/www/html/assets/uploads"]

# Container sağlık kontrolü (silent curl)
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f -s -o /dev/null http://localhost/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]