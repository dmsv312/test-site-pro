#!/bin/sh
set -e

cd /var/www/html

# Ждём готовности PostgreSQL.
echo "Ожидание PostgreSQL на ${DB_HOST:-db}:${DB_PORT:-5432}..."
until php -r '$h=getenv("DB_HOST")?:"db";$p=getenv("DB_PORT")?:"5432";$d=getenv("DB_DATABASE")?:"sitepro";$u=getenv("DB_USERNAME")?:"sitepro";$w=getenv("DB_PASSWORD")?:"secret";new PDO("pgsql:host=$h;port=$p;dbname=$d",$u,$w);' 2>/dev/null; do
    echo "  ...ждём БД"; sleep 2
done

# Освежаем статику web/ в общий том (устойчиво к пересборкам), чистим ранее опубликованные ассеты.
# web-pristine лежит вне /var/www/html, чтобы его не перекрывал bind-mount исходников.
mkdir -p web
cp -a /opt/web-pristine/. web/
rm -rf web/assets/* 2>/dev/null || true
mkdir -p web/assets runtime
chown -R www-data:www-data web runtime

# Миграции (идемпотентны).
php yii migrate --interactive=0

exec php-fpm
