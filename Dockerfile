FROM php:8.2-fpm

# Kerakli paketlarni o'rnatish
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    nano \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Composer o'rnatish
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Host user ID va Group ID ni argument sifatida qabul qilish
ARG UID=1000
ARG GID=1000

# www-data foydalanuvchisini sizning UID/GID ga o'zgartirish
RUN usermod -u $UID www-data && groupmod -g $GID www-data

# Ish katalogini o'rnatish
WORKDIR /var/www/html

# Ruxsatlarni sozlash
RUN chown -R www-data:www-data /var/www/html

# www-data foydalanuvchisi sifatida ishlash
USER www-data