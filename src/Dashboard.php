<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CronManager\CronManager;
use CronManager\TwigFactory;
use CronManager\LinuxCronAdapter;
use CronManager\WindowsCronAdapter;

$adapter = stripos(PHP_OS, 'WIN') === false
    ? new LinuxCronAdapter()
    : new WindowsCronAdapter();

$cronManager = new CronManager($adapter);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'add':
                $schedule = trim($_POST['schedule'] ?? '');
                $command = trim($_POST['command'] ?? '');
                if (empty($schedule) || empty($command)) {
                    $error = 'Schedule and command are required.';
                } elseif (!$cronManager->validateCronSchedule($schedule)) {
                    $error = 'Invalid cron schedule format.';
                } elseif ($cronManager->addTask($schedule, $command)) {
                    $message = 'Task added successfully.';
                } else {
                    $error = 'Failed to add task.';
                }
                break;

            case 'delete':
                $taskId = (int)($_POST['task_id'] ?? 0);
                if ($cronManager->deleteTask($taskId)) {
                    $message = 'Task deleted successfully.';
                } else {
                    $error = 'Failed to delete task.';
                }
                break;

            case 'update':
                $taskId = (int)($_POST['task_id'] ?? 0);
                $schedule = trim($_POST['schedule'] ?? '');
                $command = trim($_POST['command'] ?? '');
                if (empty($schedule) || empty($command)) {
                    $error = 'Schedule and command are required.';
                } elseif (!$cronManager->validateCronSchedule($schedule)) {
                    $error = 'Invalid cron schedule format.';
                } elseif ($cronManager->updateTask($taskId, $schedule, $command)) {
                    $message = 'Task updated successfully.';
                } else {
                    $error = 'Failed to update task.';
                }
                $_SESSION['updated_task_id'] = $_POST['task_id'];
                header('Location: /dashboard');
                exit;

            case 'import':
                $content = $_POST['cron_content'] ?? '';
                if (!empty($content) && $cronManager->importCron($content)) {
                    $message = 'Cron content imported successfully.';
                } else {
                    $error = 'Failed to import cron content.';
                }
                break;

            case 'execute':
                $command = trim($_POST['command'] ?? '');
                if (!empty($command)) {
                    $result = $cronManager->executeTask($command);
                    if ($result['success']) {
                        $message = "Task executed successfully in {$result['duration']}s";
                    } else {
                        $error = "Task failed with exit code {$result['exit_code']}";
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

// Prepare data
$fromSystem = isset($_GET['source']) && $_GET['source'] === 'system';
if ($fromSystem) {
    $tasks = $cronManager->getTaskDifferences(); // ← this is the safe spot
} else {
    $tasks = $cronManager->getCronTasks(false);
}

$logs = $cronManager->getTaskLogs(20);
$executions = $cronManager->getTaskExecutions(50);
$stats = $cronManager->getTaskStatistics(30);
$cronExport = $cronManager->exportCron();

$twig = TwigFactory::create();

$highlightTaskId = $_SESSION['updated_task_id'] ?? null;
unset($_SESSION['updated_task_id']);

echo $twig->render('dashboard.twig', [
    'base_url' => BASE_URL,
    'from_system' => $fromSystem,
    'crontab_available' => $cronManager->systemCrontabAvailable(),
    'username' => $_SESSION['username'] ?? null,
    'message' => $message,
    'error' => $error,
    'tasks' => $tasks,
    'highlight_id' => $highlightTaskId,
    'logs' => $logs,
    'executions' => $executions,
    'stats' => $stats,
    'cron_export' => $cronExport,
    'applied' => isset($_GET['applied']) && $_GET['applied'] == 1,
    'synced' => isset($_GET['synced']) && $_GET['synced'] == 1,
    'show_apply_button' => !$fromSystem && $cronManager->hasCrontabChanged(),
]);
