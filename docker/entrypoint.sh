#!/bin/sh
set -e

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-labhub}"
DB_PASS="${DB_PASS:-labhub}"

echo "Aguardando MySQL em ${DB_HOST}:${DB_PORT}..."
until php -r "
    new PDO(
        'mysql:host=${DB_HOST};port=${DB_PORT};charset=utf8mb4',
        '${DB_USER}',
        '${DB_PASS}'
    );
" >/dev/null 2>&1; do
    sleep 2
done
echo "MySQL disponível."

if [ ! -d vendor ]; then
    echo "Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist
fi

mkdir -p public/uploads
chmod -R 777 public/uploads 2>/dev/null || true

if [ ! -f .docker-db-init ]; then
    echo "Criando tabelas e dados iniciais..."
    php backend/database/setup.php
    php backend/database/seed.php
    touch .docker-db-init
    echo "Banco inicializado."
fi

php backend/database/migrate.php

exec "$@"
