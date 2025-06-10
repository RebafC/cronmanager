<?php

namespace CronManager;

class Housekeeping
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function rotateLog(): ?string
    {
        if (!file_exists($this->logFile) || filesize($this->logFile) === 0) {
            return null; // Nothing to rotate
        }

        $timestamp = date('Ymd_His');
        $archivePath = dirname($this->logFile) . "/cron_tasks_{$timestamp}.log";

        if (rename($this->logFile, $archivePath)) {
            return $archivePath;
        }

        return null;
    }

    public function cleanupOldLogs(int $keep = 5): int
    {
        $dir = dirname($this->logFile);
        $files = glob($dir . '/cron_tasks_*.log');
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $toDelete = array_slice($files, $keep);
        foreach ($toDelete as $file) {
            unlink($file);
        }

        return count($toDelete);
    }
}
