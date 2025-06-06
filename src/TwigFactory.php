<?php

namespace CronManager;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigFactory
{
    public static function create(): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/../templates');
        $twig = new Environment($loader, [
            'debug' => true,
            'cache' => false
        ]);

        // Register global variables
        $twig->addGlobal('base_url', '/');
        $twig->addGlobal('session', $_SESSION);

        return $twig;
    }
}
