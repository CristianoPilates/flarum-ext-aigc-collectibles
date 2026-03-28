#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Creates and returns a PDO(PHP Data Objects) connection using database settings from environment variables.
 *
 * Falls back to local defaults when variables are not defined.
 *
 * @throws PDOException If the connection cannot be established.
 */
function connect(): PDO
{
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_DATABASE') ?: 'flarum'
        ),
        getenv('DB_USERNAME') ?: 'flarum',
        getenv('DB_PASSWORD') ?: 'flarum',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/**
 * Creates a user when missing, or refreshes key fields for an existing user.
 *
 * Blind box rows are reconciled separately so the persisted count stays honest.
 *
 *
 * @return int The user ID.
 *
 * @throws PDOException If a database operation fails.
 */
function ensureUser(PDO $db, string $username, string $email, int $blindBoxCount, string $passwordHash): int
{
    $find = $db->prepare('SELECT id FROM users WHERE username = ?');
    $find->execute([$username]);
    $id = $find->fetchColumn();

    if ($id === false) {
        $insert = $db->prepare(
            'INSERT INTO users (username, email, password, is_email_confirmed, joined_at, blind_box_count)
             VALUES (?, ?, ?, 1, NOW(), ?)'
        );
        $insert->execute([$username, $email, $passwordHash, $blindBoxCount]);
        echo "[playwright-prepare] {$username} created\n";

        return (int) $db->lastInsertId();
    }

    $update = $db->prepare(
        'UPDATE users
         SET email = ?,
             password = ?,
             is_email_confirmed = 1,
             blind_box_count = GREATEST(blind_box_count, ?)
         WHERE id = ?'
    );
    $update->execute([$email, $passwordHash, $blindBoxCount, $id]);
    echo "[playwright-prepare] {$username} refreshed\n";

    return (int) $id;
}

/**
 * Counts blind boxes that are still openable by the user.
 */
function countOpenableBlindBoxes(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM blindboxes
         WHERE user_id = ?
           AND status IN ('unappraised', 'appraised')"
    );
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Creates missing blind box rows so runtime data matches the advertised balance.
 */
function createBlindBoxes(PDO $db, int $userId, int $count, string $type = 'checkin_reward'): void
{
    if ($count < 1) {
        return;
    }

    $insert = $db->prepare(
        'INSERT INTO blindboxes (user_id, type, seed, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())'
    );

    for ($i = 0; $i < $count; $i++) {
        $insert->execute([$userId, $type, bin2hex(random_bytes(32)), 'unappraised']);
    }
}

/**
 * Ensures the user has at least the requested number of unopened blind boxes and
 * then synchronizes users.blind_box_count with the actual openable rows.
 */
function ensureBlindBoxes(PDO $db, int $userId, int $minimumCount, string $type = 'checkin_reward'): int
{
    $currentCount = countOpenableBlindBoxes($db, $userId);

    if ($currentCount < $minimumCount) {
        createBlindBoxes($db, $userId, $minimumCount - $currentCount, $type);
        $currentCount = countOpenableBlindBoxes($db, $userId);
    }

    $update = $db->prepare('UPDATE users SET blind_box_count = ? WHERE id = ?');
    $update->execute([$currentCount, $userId]);

    return $currentCount;
}

/**
 * Ensures that a user belongs to the member group (group_id = 3).
 * “Member” group (ID = 3) is a built-in default group representing normal registered users.

 *
 *
 * @throws PDOException If the insert operation fails.
 */
function ensureMemberGroup(PDO $db, int $userId): void
{
    $stmt = $db->prepare('INSERT IGNORE INTO group_user (user_id, group_id) VALUES (?, 3)');
    $stmt->execute([$userId]);
}

$db = connect();
$passwordHash = password_hash('password', PASSWORD_BCRYPT);

$admin = $db->prepare(
    "UPDATE users
     SET password = ?, is_email_confirmed = 1, blind_box_count = GREATEST(blind_box_count, 10)
     WHERE username = 'admin'"
);
$admin->execute([$passwordHash]);
echo "[playwright-prepare] admin ensured with password 'password'\n";

$adminIdQuery = $db->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
$adminId = (int) $adminIdQuery->fetchColumn();
if ($adminId > 0) {
    $adminBlindBoxes = ensureBlindBoxes($db, $adminId, 10);
    echo "[playwright-prepare] admin blind boxes reconciled to {$adminBlindBoxes}\n";
}

foreach ([
    ['buyer', 'buyer@example.com', 5],
    ['seller', 'seller@example.com', 3],
] as [$username, $email, $blindBoxCount]) {
    $userId = ensureUser($db, $username, $email, $blindBoxCount, $passwordHash);
    ensureMemberGroup($db, $userId);
    $actualBlindBoxes = ensureBlindBoxes($db, $userId, $blindBoxCount);
    echo "[playwright-prepare] {$username} blind boxes reconciled to {$actualBlindBoxes}\n";
}

echo "[playwright-prepare] demo users ready\n";
