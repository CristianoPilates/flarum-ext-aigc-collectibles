<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Testing\integration\Setup\SetupScript;

require __DIR__.'/../../vendor/autoload.php';

foreach ([
    'DB_DRIVER' => getenv('FLARUM_TEST_DB_DRIVER') ?: 'mysql',
    'DB_HOST' => getenv('FLARUM_TEST_DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('FLARUM_TEST_DB_PORT') ?: '3306',
    'DB_DATABASE' => getenv('FLARUM_TEST_DB_DATABASE') ?: 'flarum_aigc_collectibles_test',
    'DB_USERNAME' => getenv('FLARUM_TEST_DB_USERNAME') ?: 'flarum',
    'DB_PASSWORD' => getenv('FLARUM_TEST_DB_PASSWORD') ?: 'flarum',
    'FLARUM_TEST_TMP_DIR_LOCAL' => getenv('FLARUM_TEST_TMP_DIR_LOCAL') ?: __DIR__.'/tmp',
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$setup = new SetupScript();

$setup->run();
