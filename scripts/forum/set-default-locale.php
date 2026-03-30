<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/forum/set-default-locale.php <locale>\n");
    exit(1);
}

$locale = trim($argv[1]);
if ($locale === '') {
    fwrite(STDERR, "Locale must not be empty.\n");
    exit(1);
}

$allowed = ['en', 'zh-hans', 'zh-Hans'];
if (!in_array($locale, $allowed, true)) {
    fwrite(STDERR, 'Unsupported locale: '.$locale."\n");
    fwrite(STDERR, 'Allowed locales: '.implode(', ', $allowed)."\n");
    exit(1);
}

$config = require getenv('FLARUM_SITE_DIR').'/config.php';
$db = $config['database'];

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['database'],
    $db['charset']
);

$pdo = new PDO($dsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$update = $pdo->prepare('UPDATE settings SET `value` = :value WHERE `key` = :key');
$update->execute([
    'key' => 'default_locale',
    'value' => $locale,
]);

echo json_encode([
    'defaultLocale' => $locale,
    'clearedCacheRequired' => true,
    'notes' => [
        'Core strings without a matching language pack will fall back to English.',
        'This extension ships both zh-hans and zh-Hans locale files.',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
