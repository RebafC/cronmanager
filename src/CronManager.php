<?php

declare(strict_types=1);

namespace CronManager;

use DateTime;
use Exception;

class CronManager
{
    private string $cronFile;
    private string $logFile;
    private bool $isWindows;

    public function __construct(string $cronFile = '', string $logFile = 'cron_tasks.log')
    {
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
        $this->cronFile = $cronFile ?: ($this->isWindows ? 'crontab.txt' : '/tmp/crontab_backup');
        $this->logFile = $logFile;

        $this->initializeFiles();
    }

    private function initializeFiles(): void
    {
        if (!file_exists($this->cronFile)) {
            touch($this->cronFile);
        }

        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }

    public function getCronTasks(bool $fromSystem = false): array
    {
        if ($fromSystem) {
            $content = $this->readSystemCrontab();
        } else {
            $content = file_exists($this->cronFile) ? file_get_contents($this->cronFile) : '';
        }

        $lines = explode("\n", $content);
        $tasks = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line !== '' && $parsed = $this->parseCronLine($line, $i)) {
                $tasks[] = $parsed;
            }
        }

        return $tasks;
    }

    private function parseCronLine(string $line, int $index): ?array
    {
        $line = trim($line);
        if (empty($line) || str_starts_with($line, '#')) {
            return null;
        }

        $parts = preg_split('/\s+/', $line, 6);
        if (count($parts) < 6) {
            return null;
        }

        $known = $this->getCronTasks(false);
        $live = $this->getCronTasks(true);

        foreach ($live as $task) {
            $task['status'] = in_array($task['command'], array_column($known, 'command')) ? 'known' : 'unknown';
        }

        return [
            'id' => $index,
            'minute' => $parts[0],
            'hour' => $parts[1],
            'day' => $parts[2],
            'month' => $parts[3],
            'weekday' => $parts[4],
            'command' => $parts[5],
            'schedule' => implode(' ', array_slice($parts, 0, 5)),
            'full_line' => $line,
            'description' => $this->generateDescription($parts[0], $parts[1], $parts[2], $parts[3], $parts[4])
        ];
    }

    private function generateDescription(string $min, string $hour, string $day, string $month, string $weekday): string
    {
        $parts = [];

        if ($min === '*') {
            $parts[] = 'every minute';
        } elseif (str_contains($min, '/')) {
            $interval = explode('/', $min)[1];
            $parts[] = "every {$interval} minutes";
        } else {
            $parts[] = "at minute {$min}";
        }

        if ($hour !== '*') {
            if (str_contains($hour, '/')) {
                $interval = explode('/', $hour)[1];
                $parts[] = "every {$interval} hours";
            } else {
                $parts[] = "at hour {$hour}";
            }
        }

        if ($day !== '*') {
            $parts[] = "on day {$day}";
        }

        if ($month !== '*') {
            $parts[] = "in month {$month}";
        }

        if ($weekday !== '*') {
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $dayName = is_numeric($weekday) ? ($days[(int)$weekday] ?? "day {$weekday}") : $weekday;
            $parts[] = "on {$dayName}";
        }

        return implode(', ', $parts);
    }

    public function addTask(string $schedule, string $command): bool
    {
        $cronLine = "{$schedule} {$command} # cronmanager\n";

        if (file_put_contents($this->cronFile, $cronLine, FILE_APPEND | LOCK_EX) !== false) {
            $this->logTask('ADDED', $cronLine);
            $this->updateSystemCron();
            return true;
        }

        return false;
    }

    public function deleteTask(int $taskId): bool
    {
        $tasks = file($this->cronFile, FILE_IGNORE_NEW_LINES);

        if (!isset($tasks[$taskId])) {
            return false;
        }

        $deletedTask = $tasks[$taskId];
        unset($tasks[$taskId]);

        if (file_put_contents($this->cronFile, implode("\n", $tasks) . "\n", LOCK_EX) !== false) {
            $this->logTask('DELETED', $deletedTask);
            $this->updateSystemCron();
            return true;
        }

        return false;
    }

    public function updateTask(int $taskId, string $schedule, string $command): bool
    {
        $tasks = file($this->cronFile, FILE_IGNORE_NEW_LINES);

        if (!isset($tasks[$taskId])) {
            return false;
        }

        $oldTask = $tasks[$taskId];
        $newTask = "{$schedule} {$command} # cronmanager";
        $tasks[$taskId] = $newTask;

        if (file_put_contents($this->cronFile, implode("\n", $tasks) . "\n", LOCK_EX) !== false) {
            $this->logTask('UPDATED', "FROM: {$oldTask} TO: {$newTask}");
            $this->updateSystemCron();
            return true;
        }

        return false;
    }

    private function updateSystemCron(): void
    {
        if (!$this->isWindows) {
            file_put_contents('/tmp/cronmanager_backup_' . date('Ymd_His') . '.cron', $this->readSystemCrontab());
            exec("crontab {$this->cronFile}");
        }
    }

    public function logTask(string $action, string $details): void
    {
        $timestamp = (new DateTime())->format('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$action}: {$details}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function getTaskLogs(int $limit = 50): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logs = file($this->logFile, FILE_IGNORE_NEW_LINES);
        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function getTaskExecutions(int $limit = 100): array
    {
        $executionFile = str_replace('.log', '_executions.log', $this->logFile);

        if (!file_exists($executionFile)) {
            return [];
        }

        $executions = [];
        $lines = file($executionFile, FILE_IGNORE_NEW_LINES);

        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $execution = json_decode($line, true);
            if ($execution) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    public function getTaskStatistics(int $days = 30): array
    {
        $executions = $this->getTaskExecutions(1000);
        $cutoffDate = (new DateTime())->modify("-{$days} days");

        $stats = [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_duration' => 0,
            'commands' => [],
            'recent_failures' => [],
            'execution_trend' => []
        ];

        $totalDuration = 0;
        $dailyStats = [];

        foreach ($executions as $execution) {
            $execDate = new DateTime($execution['timestamp']);

            if ($execDate < $cutoffDate) {
                continue;
            }

            $stats['total_executions']++;
            $totalDuration += $execution['duration'];

            if ($execution['status'] === 'SUCCESS') {
                $stats['successful_executions']++;
            } else {
                $stats['failed_executions']++;
                if (count($stats['recent_failures']) < 10) {
                    $stats['recent_failures'][] = $execution;
                }
            }

            // Command statistics
            $cmd = $execution['command'];
            if (!isset($stats['commands'][$cmd])) {
                $stats['commands'][$cmd] = [
                    'total' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'avg_duration' => 0,
                    'total_duration' => 0
                ];
            }

            $stats['commands'][$cmd]['total']++;
            $stats['commands'][$cmd]['total_duration'] += $execution['duration'];

            if ($execution['status'] === 'SUCCESS') {
                $stats['commands'][$cmd]['success']++;
            } else {
                $stats['commands'][$cmd]['failed']++;
            }

            // Daily trend
            $day = $execDate->format('Y-m-d');
            if (!isset($dailyStats[$day])) {
                $dailyStats[$day] = ['total' => 0, 'success' => 0, 'failed' => 0];
            }
            $dailyStats[$day]['total']++;
            if ($execution['status'] === 'SUCCESS') {
                $dailyStats[$day]['success']++;
            } else {
                $dailyStats[$day]['failed']++;
            }
        }

        // Calculate averages
        if ($stats['total_executions'] > 0) {
            $stats['average_duration'] = round($totalDuration / $stats['total_executions'], 3);
        }

        foreach ($stats['commands'] as $cmd => &$cmdStats) {
            if ($cmdStats['total'] > 0) {
                $cmdStats['avg_duration'] = round($cmdStats['total_duration'] / $cmdStats['total'], 3);
                $cmdStats['success_rate'] = round(($cmdStats['success'] / $cmdStats['total']) * 100, 1);
            }
        }

        $stats['execution_trend'] = $dailyStats;
        $stats['success_rate'] = $stats['total_executions'] > 0 ?
            round(($stats['successful_executions'] / $stats['total_executions']) * 100, 1) : 0;

        return $stats;
    }

    public function executeTask(string $command): array
    {
        $startTime = microtime(true);
        $output = '';
        $error = '';

        // Execute command and capture output
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin

            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);
        } else {
            $exitCode = -1;
            $error = 'Failed to start process';
        }

        $duration = microtime(true) - $startTime;

        // Log the execution
        $this->logTaskExecution($command, $exitCode, $duration, $output, $error);

        return [
            'exit_code' => $exitCode,
            'duration' => round($duration, 3),
            'output' => $output,
            'error' => $error,
            'success' => $exitCode === 0
        ];
    }

    public function trackExternalExecution(array $data): bool
    {
        $required = ['task_id', 'command', 'status', 'duration'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        $exitCode = $data['status'] === 'success' ? 0 : ($data['exit_code'] ?? 1);
        $output = $data['output'] ?? '';
        $error = $data['error'] ?? '';
        $duration = (float)$data['duration'];

        $this->logTaskExecution(
            $data['command'],
            $exitCode,
            $duration,
            $output,
            $error,
            $data['task_id']
        );

        return true;
    }

    public function logTaskExecution(string $command, int $exitCode, float $duration, string $output = '', string $error = '', string $taskId = ''): void
    {
        $executionFile = str_replace('.log', '_executions.log', $this->logFile);

        $execution = [
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            'task_id' => $taskId ?: md5($command . time()),
            'command' => $command,
            'exit_code' => $exitCode,
            'duration' => round($duration, 3),
            'output' => substr($output, 0, 1000), // Limit output length
            'error' => substr($error, 0, 1000),
            'status' => $exitCode === 0 ? 'SUCCESS' : 'FAILED',
            'source' => $taskId ? 'external' : 'manual'
        ];

        file_put_contents($executionFile, json_encode($execution) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function generateWrapperScript(): string
    {
        $baseUrl = $this->getBaseUrl();
        $apiKey = $this->getApiKey();

        return <<<BASH
#!/bin/bash

# Cron Task Wrapper Script
# Usage: ./cron-wrapper.sh "task-id" "command to execute"

TASK_ID="\$1"
COMMAND="\$2"
API_URL="{$baseUrl}/api/track-completion"
API_KEY="{$apiKey}"

if [ -z "\$TASK_ID" ] || [ -z "\$COMMAND" ]; then
    echo "Usage: \$0 <task-id> <command>"
    exit 1
fi

echo "Starting task: \$TASK_ID"
echo "Command: \$COMMAND"

START_TIME=\$(date +%s.%N)

# Execute the command and capture output
OUTPUT=\$(eval "\$COMMAND" 2>&1)
EXIT_CODE=\$?

END_TIME=\$(date +%s.%N)
DURATION=\$(echo "\$END_TIME - \$START_TIME" | bc)

# Determine status
if [ \$EXIT_CODE -eq 0 ]; then
    STATUS="success"
else
    STATUS="failed"
fi

echo "Task completed with exit code: \$EXIT_CODE"
echo "Duration: \$DURATION seconds"

# Send tracking data to API
curl -s -X POST "\$API_URL" \\
    -H "Content-Type: application/json" \\
    -H "X-API-Key: \$API_KEY" \\
    -d "{
        \"task_id\": \"\$TASK_ID\",
        \"command\": \"\$COMMAND\",
        \"status\": \"\$STATUS\",
        \"exit_code\": \$EXIT_CODE,
        \"duration\": \$DURATION,
        \"output\": \"\$(echo \"\$OUTPUT\" | head -c 1000 | sed 's/"/\\\\"/g')\",
        \"timestamp\": \"\$(date -Iseconds)\"
    }" >/dev/null 2>&1

exit \$EXIT_CODE
BASH;
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}";
    }

    private function getApiKey(): string
    {
        // Generate or retrieve API key
        $keyFile = 'api_key.txt';
        if (!file_exists($keyFile)) {
            $apiKey = bin2hex(random_bytes(32));
            file_put_contents($keyFile, $apiKey);
        } else {
            $apiKey = trim(file_get_contents($keyFile));
        }
        return $apiKey;
    }

    public function validateApiKey(string $providedKey): bool
    {
        return hash_equals($this->getApiKey(), $providedKey);
    }

    public function exportCron(): string
    {
        return file_get_contents($this->cronFile);
    }

    public function importCron(string $content): bool
    {
        if (file_put_contents($this->cronFile, $content, LOCK_EX) !== false) {
            $this->logTask('IMPORTED', 'Cron file imported');
            $this->updateSystemCron();
            return true;
        }

        return false;
    }

    public function validateCronSchedule(string $schedule): bool
    {
        $parts = explode(' ', $schedule);
        if (count($parts) !== 5) {
            return false;
        }

        $ranges = [
            [0, 59],  // minute
            [0, 23],  // hour
            [1, 31],  // day
            [1, 12],  // month
            [0, 7]    // weekday
        ];

        foreach ($parts as $index => $part) {
            if (!$this->validateCronField($part, $ranges[$index][0], $ranges[$index][1])) {
                return false;
            }
        }

        return true;
    }

    private function validateCronField(string $field, int $min, int $max): bool
    {
        if ($field === '*') {
            return true;
        }

        if (str_contains($field, '/')) {
            $parts = explode('/', $field);
            if (count($parts) !== 2) {
                return false;
            }
            return $this->validateCronField($parts[0], $min, $max) && is_numeric($parts[1]);
        }

        if (str_contains($field, '-')) {
            $parts = explode('-', $field);
            if (count($parts) !== 2) {
                return false;
            }
            return is_numeric($parts[0]) && is_numeric($parts[1]) &&
                    (int)$parts[0] >= $min && (int)$parts[1] <= $max &&
                    (int)$parts[0] <= (int)$parts[1];
        }

        if (str_contains($field, ',')) {
            $parts = explode(',', $field);
            foreach ($parts as $part) {
                if (!$this->validateCronField(trim($part), $min, $max)) {
                    return false;
                }
            }
            return true;
        }

        return is_numeric($field) && (int)$field >= $min && (int)$field <= $max;
    }

    public function readSystemCrontab(): string
    {
        return trim(shell_exec('crontab -l 2>/dev/null'));
    }

    public function syncFromSystemCrontab(): bool
    {
        $content = $this->readSystemCrontab();

        $lines = explode("\n", $content);
        $ownedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Append marker if not already present
            if (!str_contains($line, '# cronmanager')) {
                $line .= ' # cronmanager';
            }

            $ownedLines[] = $line;
        }

        return $this->importCron(implode("\n", $ownedLines));
    }

    public function systemCrontabAvailable(): bool
    {
        return !$this->isWindows && trim(shell_exec('which crontab')) !== '';
    }
}
