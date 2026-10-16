FROM php:8.3-apache

LABEL org.opencontainers.image.title="PHP Forum System" \
      org.opencontainers.image.description="A lightweight, modern, and security-hardened forum built with PHP & MariaDB" \
      org.opencontainers.image.url="https://github.com/aardaakpinar/php-forum" \
      org.opencontainers.image.source="https://github.com/aardaakpinar/php-forum" \
      org.opencontainers.image.licenses="MIT"

# Sistem bağımlılıklarını kur (healthcheck için curl, pdo_mysql ve apache modülleri)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Uygulama kaynak kodlarını kopyala
WORKDIR /var/www/html
COPY . /var/www/html/

# Entrypoint scriptini kur ve izinleri ayarla
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
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
