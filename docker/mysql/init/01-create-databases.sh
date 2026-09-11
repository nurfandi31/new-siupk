#!/bin/bash
set -e

# Read credentials from the same env vars the Laravel app uses.
# Defaults match .env.example so "docker compose up" works out of the box.
APP_USER="${PLATFORM_DB_USERNAME:-root}"
APP_PASS="${PLATFORM_DB_PASSWORD:-}"
PLATFORM_DB="${PLATFORM_DB_DATABASE:-siupk_platform}"
TENANT_DB="${TENANT_DB_DATABASE:-siupk_shard_local}"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${PLATFORM_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
    CREATE DATABASE IF NOT EXISTS \`${TENANT_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

    CREATE USER IF NOT EXISTS '${APP_USER}'@'%' IDENTIFIED BY '${APP_PASS}';
    GRANT ALL PRIVILEGES ON \`${PLATFORM_DB}\`.* TO '${APP_USER}'@'%';
    GRANT ALL PRIVILEGES ON \`${TENANT_DB}\`.* TO '${APP_USER}'@'%';
    GRANT ALL PRIVILEGES ON \`${TENANT_DB}_%\`.* TO '${APP_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL