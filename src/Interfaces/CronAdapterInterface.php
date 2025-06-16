<?php

namespace CronManager\Interfaces;

interface CronAdapterInterface
{
    public function getTasks(): array;
    public function writeTasks(array $lines): bool;
    public function getRaw(): string;
    public function update(): bool;
}
