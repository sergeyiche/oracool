FROM php:8.2-fpm-alpine

# Установка системных зависимостей
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    bash \
    supervisor

# Установка PHP расширений
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    mbstring \
    opcache

# Установка Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Настройка рабочей директории
WORKDIR /app

# Копирование composer файлов
COPY composer.json composer.lock* ./

# Установка зависимостей (без dev для продакшена)
ARG APP_ENV=prod
RUN if [ "$APP_ENV" = "dev" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist; \
    else \
        composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader; \
    fi

# Копирование остальных файлов приложения
COPY . .

# Генерация autoloader
RUN composer dump-autoload --optimize

# Создание необходимых директорий
RUN mkdir -p var/cache var/log var/sessions \
    && chmod -R 777 var

# Настройка PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Expose порт
EXPOSE 9000

CMD ["php-fpm"]
