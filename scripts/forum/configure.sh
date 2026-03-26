#!/usr/bin/env bash
set -euo pipefail

site_dir="${FLARUM_SITE_DIR:?FLARUM_SITE_DIR is required}"
config_file="$site_dir/config.php"

if [ ! -f "$config_file" ]; then
  echo "[forum-configure] config not found: $config_file" >&2
  exit 1
fi

php <<'PHP'
<?php
$configFile = getenv('FLARUM_SITE_DIR') . '/config.php';
$config = require $configFile;

$config['debug'] = true;
$config['url'] = getenv('FORUM_URL') ?: ($config['url'] ?? 'http://127.0.0.1:8080');
$config['database']['host'] = getenv('DB_HOST') ?: ($config['database']['host'] ?? '127.0.0.1');
$config['database']['port'] = (int) (getenv('DB_PORT') ?: ($config['database']['port'] ?? 3306));
$config['database']['database'] = getenv('DB_DATABASE') ?: ($config['database']['database'] ?? 'flarum');
$config['database']['username'] = getenv('DB_USERNAME') ?: ($config['database']['username'] ?? 'flarum');
$config['database']['password'] = getenv('DB_PASSWORD') ?: ($config['database']['password'] ?? 'flarum');
$config['queue']['driver'] = 'sync';

$export = "<?php return " . var_export($config, true) . ";\n";

if (file_put_contents($configFile, $export) === false) {
    fwrite(STDERR, "[forum-configure] failed to write config: {$configFile}" . PHP_EOL);
    exit(1);
}

echo "[forum-configure] debug=true url=" . $config['url'] . PHP_EOL;
PHP
