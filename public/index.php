<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use CronManager\Auth;

session_start();

$route = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

$auth = new Auth();

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
        require __DIR__ . '/../src/dashboard.php';
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

        // more routes here (e.g., register, forgot, change)
    default:
        http_response_code(404);
        echo '404 Not Found';
}
