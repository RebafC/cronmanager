<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use CronManager\Auth;
use CronManager\CronManager;
use CronManager\LinuxCronAdapter;
use CronManager\WindowsCronAdapter;

session_start();

$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$auth = new Auth();
$adapter = stripos(PHP_OS, 'WIN') === false
    ? new LinuxCronAdapter()
    : new WindowsCronAdapter();

$cronManager = new CronManager($adapter);

switch ($route) {
    case '':
    case 'login':
        $auth->handleLogin(); // showLoginForm() + login logic
        break;

    case 'logout':
        $auth->logout();
        break;

    case 'dashboard':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }
        require __DIR__ . '/../src/Dashboard.php';
        break;

    case 'invite':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }
        $auth->handleInvite();
        break;

    case 'register':
        $auth->handleRegister();
        break;

    case 'logout':
        session_destroy();
        header('Location: /login');
        break;

    case 'forgot':
        $auth->handleForgot();
        break;

    case 'reset':
        $auth->handleReset();
        break;

    case 'sync-crontab':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }

        $cronManager->syncFromSystemCrontab();
        header('Location: /dashboard?source=file&synced=1');
        exit;

    case 'apply-crontab':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }

        // uses your cronFile
        $cronManager->updateSystemCron();
        header('Location: /dashboard?source=system&applied=1');
        exit;

    case 'users':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }
        $auth->handleUserList();
        break;

    case 'delete-user':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }
        $auth->handleUserDelete();
        break;

    case 'toggle-user':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }
        $auth->handleUserToggle();
        break;

    case 'log':
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }

        require __DIR__ . '/../src/Log.php';

        break;

        // more routes here (e.g., register, forgot, change)

    default:
        http_response_code(404);
        echo '404 Not Found';
}
