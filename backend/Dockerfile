# Yii2 (PHP-FPM). nginx из web-сервиса отдаёт статику из общего тома web/ и проксирует сюда PHP.
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libonig-dev libicu-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql bcmath mbstring zip intl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# php-fpm по умолчанию очищает окружение воркеров — оставляем его, чтобы getenv() видел DB_*/YII_*.
RUN printf '[www]\nclear_env = no\n' > /usr/local/etc/php-fpm.d/zz-clearenv.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Код целиком до composer install, чтобы post-install скрипты Yii отработали.
COPY . .
RUN composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader \
    && cp -a web /opt/web-pristine \
    && chmod +x docker/entrypoint.sh \
    && mkdir -p runtime web/assets \
    && chown -R www-data:www-data runtime web

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
