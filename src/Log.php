<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use CronManager\TwigFactory;

$log_size = 0;

$entries = [];

if (file_exists(LOG_FILE)) {
    $entries = array_reverse(file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $log_size = round(filesize(LOG_FILE) / 1024, 1);
}

$twig = TwigFactory::create();

echo $twig->render('log.twig', [
    'entries' => $entries,
    // in KB
    'log_size' => $log_size,
]);
