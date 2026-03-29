#!/usr/bin/env bash
set -euo pipefail

site_dir="${FLARUM_SITE_DIR:?FLARUM_SITE_DIR is required}"

if [ ! -f "$site_dir/flarum" ]; then
  echo "[forum-install] creating site in $site_dir"
  composer create-project "flarum/flarum:${FLARUM_VER:-^2.0.0}" --stability=beta "$site_dir"
  cd "$site_dir"
  composer require flarum/extension-manager:"*"
  echo "[forum-install] site created"
fi

if [ -f "$site_dir/config.php" ]; then
  echo "[forum-install] site already installed: $site_dir"
  exit 0
fi

install_file="$(mktemp)"
trap 'rm -f "$install_file"' EXIT

php -r '
$config = [
    "debug" => true,
    "baseUrl" => getenv("FORUM_URL") ?: "http://127.0.0.1:8080",
    "databaseConfiguration" => [
        "driver" => getenv("DB_DRIVER") ?: "mysql",
        "host" => getenv("DB_HOST") ?: "127.0.0.1",
        "port" => (int) (getenv("DB_PORT") ?: 3306),
        "database" => getenv("DB_DATABASE") ?: "flarum",
        "username" => getenv("DB_USERNAME") ?: "flarum",
        "password" => getenv("DB_PASSWORD") ?: "flarum",
        "prefix" => getenv("DB_PREFIX") ?: "",
    ],
    "adminUser" => [
        "username" => getenv("FLARUM_ADMIN_USERNAME") ?: "admin",
        "password" => getenv("FLARUM_ADMIN_PASSWORD") ?: "password",
        "email" => getenv("FLARUM_ADMIN_EMAIL") ?: "admin@example.com",
    ],
    "settings" => [
        "forum_title" => getenv("FLARUM_FORUM_TITLE") ?: "AIGC Collectibles",
    ],
];

file_put_contents($argv[1], json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$install_file"

cd "$site_dir"
php flarum install --file "$install_file" --config config.php

echo "[forum-install] site installed (non-interactive CLI)"

