#!/usr/bin/env php
<?php
/**
 * seed-demo.php — Create demo users and set passwords for testing
 * Usage: php scripts/seed-demo.php
 */

$db = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=flarum',
    'flarum',
    'flarum',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$password = password_hash('password', PASSWORD_BCRYPT);

// Ensure admin user
$db->exec("UPDATE users SET password = '{$password}', is_email_confirmed = 1 WHERE username = 'admin'");
echo "✓ admin password set to 'password'\n";

// Ensure buyer user
$stmt = $db->query("SELECT id FROM users WHERE username = 'buyer'");
if (!$stmt->fetch()) {
    $db->exec("INSERT INTO users (username, email, password, is_email_confirmed, joined_at, blind_box_count)
               VALUES ('buyer', 'buyer@example.com', '{$password}', 1, NOW(), 5)");
    echo "✓ buyer created with 5 blind boxes\n";
} else {
    $db->exec("UPDATE users SET password = '{$password}', is_email_confirmed = 1, blind_box_count = GREATEST(blind_box_count, 5) WHERE username = 'buyer'");
    echo "✓ buyer password set, ensured ≥5 blind boxes\n";
}

// Ensure seller user
$stmt = $db->query("SELECT id FROM users WHERE username = 'seller'");
if (!$stmt->fetch()) {
    $db->exec("INSERT INTO users (username, email, password, is_email_confirmed, joined_at, blind_box_count)
               VALUES ('seller', 'seller@example.com', '{$password}', 1, NOW(), 3)");
    echo "✓ seller created with 3 blind boxes\n";
} else {
    $db->exec("UPDATE users SET password = '{$password}', is_email_confirmed = 1 WHERE username = 'seller'");
    echo "✓ seller password set\n";
}

// Ensure all users are in group 3 (Members)
foreach (['buyer', 'seller'] as $username) {
    $id = $db->query("SELECT id FROM users WHERE username = '{$username}'")->fetchColumn();
    if ($id) {
        $db->exec("INSERT IGNORE INTO group_user (user_id, group_id) VALUES ({$id}, 3)");
    }
}
echo "✓ All users added to Members group\n";

// Give admin enough blind boxes for testing
$db->exec("UPDATE users SET blind_box_count = GREATEST(blind_box_count, 10) WHERE username = 'admin'");
echo "✓ admin ensured ≥10 blind boxes\n";

// Summary
echo "\n=== Demo Users ===\n";
$stmt = $db->query("SELECT id, username, email, blind_box_count FROM users ORDER BY id");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("  %d. %s (%s) — %d blind boxes\n", $row['id'], $row['username'], $row['email'], $row['blind_box_count']);
}
echo "\nAll passwords: 'password'\n";
