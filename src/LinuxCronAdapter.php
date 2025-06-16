<?php

namespace CronManager;

use CronManager\Interfaces\CronAdapterInterface;

class LinuxCronAdapter implements CronAdapterInterface
{
    public function getTasks(): array
    {
        return explode("\n", shell_exec('crontab -l 2>/dev/null'));
    }

    public function writeTasks(array $lines): bool
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, implode("\n", $lines));
        shell_exec("crontab $tempFile");
        unlink($tempFile);
        return true;
    }

    public function getRaw(): string
    {
        return shell_exec('crontab -l 2>/dev/null');
    }

    public function update(): bool
    {
        // sync from crontab.txt etc.
        return true;
    }

    public function isAvailable(): bool
    {
        return trim(shell_exec('which crontab')) !== '';
    }
}
