
# Cron Task Manager

A PHP 8.2 application for managing cron tasks with a web interface, following PSR-12 standards.

## Features

- ✅ View, add, edit, and delete cron tasks
- ✅ Cross-platform compatibility (Linux/Windows)
- ✅ Activity logging and tracking
- ✅ Import/Export cron configurations
- ✅ Cron schedule validation
- ✅ Clean web interface
- ✅ PSR-12 compliant code

## Setup

### Linux (Production)
1. Install PHP 8.2+ and Apache/Nginx
2. Clone/upload files to web directory
3. Run `composer install`
4. Set proper permissions for log files
5. Access via web browser

### Windows (Testing)

1. Install PHP 8.2+ and a local server (XAMMP/WAMP)
2. Place files in web directory
3. Create `crontab.txt` with sample cron content
4. Run `composer install`
5. Access via localhost

## Usage

1. **View Tasks**: See all current cron tasks with human-readable descriptions
2. **Add Tasks**: Use the web form with cron schedule validation
3. **Edit Tasks**: Click edit button to modify existing tasks
4. **Delete Tasks**: Remove tasks with confirmation
5. **Import/Export**: Backup and restore cron configurations
6. **Activity Logs**: Track all changes made to cron tasks

## File Structure

```dir
/project-root
├── composer.json
├── src/
│   └── CronManager.php
├── public/
│   └── index.php
├── crontab.txt (Windows testing)
├── cron_tasks.log (auto-created)
└── README.md
```

## Cron Schedule Format

- `* * * * *` - minute hour day month weekday
- `0 0 * * *` - Daily at midnight
- `*/15 * * * *` - Every 15 minutes
- `0 9 * * 1` - Every Monday at 9 AM

## Security Notes

- Validate all inputs
- Use file locking for concurrent access
- Sanitize commands before execution
- Consider access restrictions for production use
