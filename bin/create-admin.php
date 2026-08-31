<?php

declare(strict_types=1);

/**
 * Create or reset the initial super-admin user. Password is hashed with
 * password_hash() — never stored or logged in plain text (SECURITY_PLAN §5).
 *
 * Usage:
 *   php bin/create-admin.php "Admin Name" admin@example.com "StrongPassphrase"
 * If args are omitted, values are read from env: ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
 * The user is flagged must_change_password=1 and granted the super_admin role.
 */

use App\Core\App;
use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("create-admin.php is a CLI script.\n");
}

/** @var App $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';
/** @var Database $db */
$db = $app->container()->get(Database::class);

$name     = $argv[1] ?? (string) getenv('ADMIN_NAME')     ?: 'Administrator';
$email    = $argv[2] ?? (string) getenv('ADMIN_EMAIL')    ?: '';
$password = $argv[3] ?? (string) getenv('ADMIN_PASSWORD') ?: '';

$email = strtolower(trim($email));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "✗ A valid email is required (arg 2 or ADMIN_EMAIL).\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "✗ Password must be at least 10 characters (arg 3 or ADMIN_PASSWORD).\n");
    exit(1);
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Ensure the super_admin role exists (seeds normally create it).
    $db->statement(
        "INSERT INTO roles (`key`,`name`,`description`,`is_system`)
         VALUES ('super_admin','Super Admin','Full access',1)
         ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)"
    );
    $roleId = (int) ($db->selectOne("SELECT id FROM roles WHERE `key`='super_admin'")['id'] ?? 0);

    $existing = $db->selectOne('SELECT id FROM users WHERE email = :e', ['e' => $email]);

    if ($existing) {
        $userId = (int) $existing['id'];
        $db->statement(
            'UPDATE users SET name=:n, password_hash=:p, is_active=1, must_change_password=1 WHERE id=:id',
            ['n' => $name, 'p' => $hash, 'id' => $userId]
        );
        echo "✓ Updated existing admin user (id {$userId}).\n";
    } else {
        $userId = (int) $db->insert(
            'INSERT INTO users (name,email,password_hash,is_active,must_change_password)
             VALUES (:n,:e,:p,1,1)',
            ['n' => $name, 'e' => $email, 'p' => $hash]
        );
        echo "✓ Created admin user (id {$userId}).\n";
    }

    if ($roleId > 0) {
        $db->statement(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)',
            ['u' => $userId, 'r' => $roleId]
        );
    }

    echo "  Email: {$email}\n  Role:  super_admin\n  Note:  must change password on first login.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "✗ Failed: {$e->getMessage()}\n");
    exit(1);
}

exit(0);
