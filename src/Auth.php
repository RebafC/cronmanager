<?php

namespace CronManager;

use PDO;
use CronManager\TwigFactory;

require_once __DIR__ . '/../config.php';

class Auth
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite:' . DBASE);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

        $twig = TwigFactory::create();

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

    public function handleInvite(): void
    {
        $twig = TwigFactory::create();

        $message = '';
        $error = '';
        $inviteLink = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

                $stmt = $this->pdo->prepare("
                INSERT INTO `password_resets` (`email`, `token`, `type`, `expires_at`)
                VALUES (:email, :token, 'invite', :expires)
            ");
                $stmt->execute([
                    'email' => $email,
                    'token' => $token,
                    'expires' => $expires
                ]);

                $inviteLink = '/register?token=' . $token;
                $message = 'Invitation created.';

                Mailer::send(
                    $email,
                    'Your CronManager Invite',
                    "You're invited to create an account. Click here to register:\n\n" . BASE_URL . $inviteLink
                );
                $message = 'Invitation created.';
            }
        }

        echo $twig->render('invite.twig', [
            'message' => $message,
            'error' => $error,
            'invite_link' => $inviteLink
        ]);
    }

    public function handleRegister(): void
    {
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
        $twig = \CronManager\TwigFactory::create();

        $message = '';
        $error = '';
        $token = $_GET['token'] ?? '';
        $email = '';
        $showForm = false;

        if ($token) {
            // Look up valid, unused invite token
            $stmt = $this->pdo->prepare("
            SELECT * FROM password_resets
            WHERE token = :token AND type = 'invite' AND used = 0 AND expires_at > datetime('now')
        ");
            $stmt->execute(['token' => $token]);
            $invite = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($invite) {
                $email = $invite['email'];
                $showForm = true;

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $password = $_POST['password'] ?? '';
                    $confirm = $_POST['confirm_password'] ?? '';

                    if (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters.';
                    } elseif ($password !== $confirm) {
                        $error = 'Passwords do not match.';
                    } elseif (!$this->createUser($email, $password)) {
                        $error = 'User already exists or creation failed.';
                    } else {
                        // Mark token as used
                        $update = $this->pdo->prepare("UPDATE `password_resets` SET `used` = 1 WHERE `id` = :id");
                        $update->execute(['id' => $invite['id']]);

                        // Auto-login
                        $stmt = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `username` = :email");
                        $stmt->execute(['email' => $email]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $email;

                            header('Location: /dashboard');
                            exit;
                        }
                    }
                }
            } else {
                $error = 'Invalid or expired invite link.';
            }
        } else {
            $error = 'Missing token.';
        }

        echo $twig->render('register.twig', [
            'token' => $token,
            'email' => $email,
            'message' => $message,
            'error' => $error,
            'show_form' => $showForm,
        ]);
    }

    public function handleForgot(): void
    {
        $twig = \CronManager\TwigFactory::create();

        $message = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if user exists
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = :email");
                $stmt->execute(['email' => $email]);
                $exists = $stmt->fetch();

                if ($exists) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 day'));

                    $stmt = $this->pdo->prepare("
                    INSERT INTO password_resets (email, token, type, expires_at)
                    VALUES (:email, :token, 'reset', :expires)
                ");
                    $stmt->execute([
                        'email' => $email,
                        'token' => $token,
                        'expires' => $expires
                    ]);

                    $link = BASE_URL . '/reset?token=' . $token;
                    Mailer::send(
                        $email,
                        'Reset your CronManager password',
                        "Click here to reset your password:\n\n$link"
                    );

                    $message = 'A reset link has been sent if your email is in our system.';
                } else {
                    $message = 'A reset link has been sent if your email is in our system.';
                    // Don't reveal if user exists
                }
            }
        }

        echo $twig->render('forgot.twig', [
            'message' => $message,
            'error' => $error
        ]);
    }
    public function handleReset(): void
    {
        $twig = \CronManager\TwigFactory::create();

        $token = $_GET['token'] ?? '';
        $message = '';
        $error = '';
        $showForm = false;

        if ($token) {
            $stmt = $this->pdo->prepare("
            SELECT * FROM `password_resets`
            WHERE `token` = :token AND `type` = 'reset' AND `used` = 0 AND `expires_at` > datetime('now')
        ");
            $stmt->execute(['token' => $token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reset) {
                $showForm = true;

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $password = $_POST['password'] ?? '';
                    $confirm = $_POST['confirm_password'] ?? '';

                    if (strlen($password) < 6) {
                        $error = 'Password must be at least 6 characters.';
                    } elseif ($password !== $confirm) {
                        $error = 'Passwords do not match.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $update = $this->pdo->prepare(
                            "UPDATE `users` SET `password_hash` = :hash WHERE `username` = :email"
                        );
                        $update->execute([
                            'hash' => $hash,
                            'email' => $reset['email']
                        ]);

                        $markUsed = $this->pdo->prepare("UPDATE `password_resets` SET `used` = 1 WHERE `id` = :id");
                        $markUsed->execute(['id' => $reset['id']]);

                        // Optionally log them in
                        $stmt = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `username` = :email");
                        $stmt->execute(['email' => $reset['email']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user) {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $reset['email'];
                            header('Location: /dashboard');
                            exit;
                        }
                    }
                }
            } else {
                $error = 'Invalid or expired reset token.';
            }
        } else {
            $error = 'Missing token.';
        }

        echo $twig->render('reset.twig', [
            'token' => $token,
            'message' => $message,
            'error' => $error,
            'show_form' => $showForm
        ]);
    }
    public function handleUserList(): void
    {
        $twig = TwigFactory::create();

        $stmt = $this->pdo->query("SELECT `id`, `username`, `active` FROM `users` ORDER BY `id`");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo $twig->render('users.twig', [
            'users' => $users,
            'message' => $_GET['message'] ?? '',
        ]);
    }
    public function handleUserDelete(): void
    {
        $id = $_POST['id'] ?? null;
        if ($id && $id != $_SESSION['user_id']) {
            $stmt = $this->pdo->prepare("DELETE FROM `users` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
        }
        header('Location: /users?message=User deleted');
        exit;
    }

    public function handleUserToggle(): void
    {
        $id = $_POST['id'] ?? null;
        if ($id && $id != $_SESSION['user_id']) {
            $stmt = $this->pdo->prepare("UPDATE `users` SET `active` = NOT `active` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);
        }
        header('Location: /users?message=User status updated');
        exit;
    }

}
