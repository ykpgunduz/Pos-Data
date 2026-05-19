# PHP 8.4 imajını kullanalım
FROM php:8.4-fpm

# ICU ve PostgreSQL için gerekli araçları yükleyin
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP eklentilerini yükleyin
RUN docker-php-ext-install intl \
    && docker-php-ext-configure zip --with-zip \
    && docker-php-ext-install zip pdo pdo_pgsql pgsql

# Composer'ı yükleyin
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Çalışma dizinini belirleyin
WORKDIR /var/www

# Projenizi çalışma dizinine kopyalayın
COPY . .

# Gerekirse proje bağımlılıklarını yükleyin
RUN composer install

# Başlangıç scriptini kopyala ve çalıştırılabilir yap
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Container başlarken migration çalıştır, sonra sunucuyu başlat
CMD ["/usr/local/bin/start.sh"]
