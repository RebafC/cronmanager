<?php

require __DIR__ . '/../config.php';

$pdo = new PDO('sqlite:' . DBASE);

$stmt = $pdo->query("SELECT `name` FROM `sqlite_master` WHERE `type`='table' AND `name`='users'");
if (!$stmt->fetch()) {
    $pdo->exec(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `username` TEXT UNIQUE NOT NULL,
            `password_hash` TEXT NOT NULL,
            `active` INTEGER DEFAULT 1,
            `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
        );
        SQL
    );
}
$stmt = $pdo->query("SELECT `name` FROM `sqlite_master` WHERE `type`='table' AND `name`='password_resets'");
if (!$stmt->fetch()) {
    $pdo->exec(
        <<<SQL
        CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `email` TEXT NOT NULL,
            `token` TEXT NOT NULL,
            `type` TEXT NOT NULL, -- 'invite' or 'reset'
            `expires_at` TEXT,
            `used` INTEGER DEFAULT 0
        );
        SQL
    );
//  $this->createUser('admin', 'secret');
}

// Check if users already exist
$stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
if ($stmt->fetchColumn() > 0) {
    echo "✅ Users already exist. Nothing to do.\n";
    exit;
}

echo "🔧 Initial user setup\n";
echo "Email: ";
$email = trim(fgets(STDIN));

echo "Password: ";
system('stty -echo');
$password = trim(fgets(STDIN));
system('stty echo');
echo "\n";

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO `users` (`username`, `password_hash`, `active`) VALUES (:email, :hash, 1)");
$stmt->execute([
    'email' => $email,
    'hash' => $hash
]);

echo "✅ User '$email' created successfully.\n";
