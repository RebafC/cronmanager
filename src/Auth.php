<?php

namespace CronManager;

use PDO;

require_once __DIR__ . '/../config.php';

class Auth
{
    private PDO $pdo;

    public function __construct()
    {
        $dbase = __DIR__ . '/../data//' . 'users.db';
        $this->pdo = new PDO('sqlite:' . $dbase);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->init();
    }

    private function init(): void
    {
        $this->pdo->exec(
            <<<SQL
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `username` TEXT UNIQUE NOT NULL,
                `password_hash` TEXT NOT NULL,
                `created_at` TEXT DEFAULT CURRENT_TIMESTAMP
            );
            SQL
        );
        //  $this->createUser('admin', 'secret');
    }

    public function login(string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare("SELECT `id`, `password_hash` FROM `users` WHERE `username` = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            return true;
        }

        return false;
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: /login');
        exit;
    }

    public function createUser(string $username, string $password): bool
    {
        if (empty($username) || empty($password)) {
            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO `users` (`username`, `password_hash`) VALUES (:username, :hash)");

        try {
            return $stmt->execute([
                'username' => $username,
                'hash' => $hash
            ]);
        } catch (\PDOException $e) {
            return false; // Username probably exists
        }
    }

    public function changePassword(int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `id` = :id");
        return $stmt->execute([
            'hash' => $hash,
            'id' => $userId
        ]);
    }

    public function forgotPassword(string $username): bool
    {
        // Placeholder: just log an event or send email in future
        return false;
    }

    public function handleLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
        $twig = new \Twig\Environment($loader);

        $loginAttempted = isset($_POST['login']);
        $loginSuccess = false;

        if ($loginAttempted) {
            $loginSuccess = $this->login($_POST['username'] ?? '', $_POST['password'] ?? '');
            if ($loginSuccess) {
                header('Location: /dashboard');
                exit;
            }
        }

        echo $twig->render('login.twig', [
            'base_url' => BASE_URL,
            'login_attempted' => $loginAttempted,
            'login_success' => $loginSuccess,
        ]);
    }
}
