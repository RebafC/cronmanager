<?php

namespace CronManager;

use CronManager\Interfaces\CronAdapterInterface;

class WindowsCronAdapter implements CronAdapterInterface
{
    private string $mockFile;

    public function __construct()
    {
        $this->mockFile = CRON_FILE;
    }

    public function getTasks(): array
    {
        return file_exists($this->mockFile)
            ? file($this->mockFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : [];
    }

    public function writeTasks(array $lines): bool
    {
        return file_put_contents($this->mockFile, implode("\n", $lines)) !== false;
    }

    public function getRaw(): string
    {
        return file_exists($this->mockFile)
            ? file_get_contents($this->mockFile)
            : '';
    }

    public function update(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return file_exists($this->mockFile);
    }
}
