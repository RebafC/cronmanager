#!/usr/bin/env php
<?php

require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Housekeeping.php';

use CronManager\Housekeeping;

$housekeeper = new Housekeeping(LOG_FILE);

echo "[INFO] Rotating cron log...\n";
$archive = $housekeeper->rotateLog();

if ($archive) {
    echo "✅ Log rotated to: $archive\n";
    $removed = $housekeeper->cleanupOldLogs(5);
    echo "🧹 Removed $removed old log(s).\n";
// } else {
//     echo "ℹ️ Nothing to rotate.\n";
}
