<?php

define(
    'BASE_URL',
    PHP_OS_FAMILY === 'Windows'
        ? 'http://localhost:8000'
        : 'http://cronmanager.cricketcrowd.com'
);

define('DBASE', __DIR__ . '/data/cronmmanager.db');
