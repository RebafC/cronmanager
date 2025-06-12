<?php

// 4 hours
ini_set('session.gc_maxlifetime', 14400);
// match cookie to session
session_set_cookie_params(14400);

define(
    'BASE_URL',
    PHP_OS_FAMILY === 'Windows'
        ? 'http://localhost:8000'
        : 'http://cronmanager.cricketcrowd.com'
);

define('DBASE', __DIR__ . '/data/cronmmanager.db');

define('LOG_FILE', __DIR__ . '/logs/cron_tasks.log');

// Set to true if CronWrapper is installed
define('CRONWRAPPER', false);
// full path to CronWrapper status page
define('CRONWRAPPER_URL', 'https://example.com/cronwrapper/status');
