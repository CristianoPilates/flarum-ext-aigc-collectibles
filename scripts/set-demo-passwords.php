<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$site = require '/home/donk/development/flarum-site/site.php';
$app = $site->bootApp();

$passwords = [
    'admin' => 'AdminPass123!',
    'buyer' => 'BuyerPass123!',
];

foreach ($passwords as $username => $password) {
    $user = \Flarum\User\User::query()->where('username', $username)->first();

    if (! $user) {
        fwrite(STDERR, "User not found: {$username}\n");
        exit(1);
    }

    $user->changePassword($password);
    $user->is_email_confirmed = true;
    $user->save();

    echo "Updated password for {$username}\n";
}
