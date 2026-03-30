<?php

declare(strict_types=1);

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

$statement = $pdo->query(
    "SELECT `key`, `value` FROM settings WHERE `key` IN ('default_locale', 'extensions_enabled') ORDER BY `key`"
);

$settings = [];
foreach ($statement->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}

$extensions = json_decode($settings['extensions_enabled'] ?? '[]', true);
if (!is_array($extensions)) {
    $extensions = [];
}

$localeFiles = glob(__DIR__.'/../../resources/locale/*.yml') ?: [];
$localeCodes = array_map(
    static fn (string $path) => pathinfo($path, PATHINFO_FILENAME),
    $localeFiles
);
sort($localeCodes);

echo json_encode([
    'defaultLocale' => $settings['default_locale'] ?? 'en',
    'enabledExtensions' => $extensions,
    'extensionLocaleFiles' => $localeCodes,
    'notes' => [
        'This extension ships locale files via Extend\\Locales.',
        'For full-site Chinese UI, the forum still needs a compatible Flarum 2 language pack.',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
