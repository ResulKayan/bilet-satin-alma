FROM php:8.1-apache

# SQLite ve gerekli extension'ları kur
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite

# Apache modüllerini aktif et
RUN a2enmod rewrite

# Proje dosyalarını kopyala
COPY src/ /var/www/html/

# Veritabanı dizini için izinler
RUN mkdir -p /var/www/html/database && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

WORKDIR /var/www/html
