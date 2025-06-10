# 🕒 CronManager

**CronManager** is a lightweight, admin-facing web app for managing and monitoring cron jobs.

It provides a clear dashboard, safe sync with the system crontab, and user authentication — making cron easier to work with for small teams, VPS users, and cPanel environments.

---

## ✨ Features

- ✅ **Web-based interface** to view, add, edit, delete cron tasks
- ✅ **System crontab sync** (read-only + apply with safety tagging)
- ✅ **Init script** to set up the first user via CLI
- ✅ **User login system** (SQLite-based)
- ✅ **Email invitations** with secure registration tokens
- ✅ **Password reset** via email token
- ✅ **Task execution logging**
- ✅ **Highlight recently updated tasks**
- ✅ **Log rotation + archival with retention**
- ✅ **Smart UI** (spinners, transitions, warnings on long commands)

---

## 🚀 Getting Started

### ✅ 1. Clone the repo

```bash
git clone https://github.com/your-username/cronmanager.git
cd cronmanager
```

### ✅ 2. Install dependencies

```bash
composer install
```

### ✅ 3. Set up environment

Set your document root to the `/public` directory.

### ✅ 4. Run the init script (first-time only)

```bash
php scripts/init.php
```

You'll be prompted to create the first user account (email + password).

---

## 🛠 Usage

### 📋 Dashboard

Once logged in, the dashboard lets you:

- Add new cron tasks (with schedule + command)
- Edit existing entries
- Delete tasks
- Execute manually (if permitted)
- View system crontab (`?source=system`)
- Push changes back to system (if crontab is writable)

### 🔁 Syncing with system crontab

- Only lines tagged with `# cronmanager` are imported or managed
- Other system-level cron jobs (e.g. from cPanel) are left untouched
- You can safely view live system crontab from the UI

### 📄 Cron Task Log

- Visit `/log` to see task execution entries
- Log rotation runs via cron (see below)
- Older logs are archived with a retention limit (default: 5)

---

## 🧹 Log Rotation (Housekeeping)

### To rotate logs manually:

```bash
php scripts/rotate_log.php
```

### Example cron task to rotate logs daily:

```cron
0 0 * * * /usr/bin/php /path/to/scripts/rotate_log.php # cronmanager
```

---

## 🔐 Authentication & Access

- Login required for all routes except `/login`, `/register`, and `/reset`
- Public registration is disabled
- Admins can send invite links via email (token expires after 1 day)

---

## 🧪 Development Notes

- Compatible with Linux servers and Windows dev environments (i.e. testing only)
- Crontab detection logic adapts to platform (`PHP_OS`)
- All app-managed tasks are written to `data/crontab.txt`
- Edits made via the UI are highlighted on save
- Supports log viewer, but not yet a full log diff viewer (planned)

---

## 📂 File Structure

```
├── config.php
├── src/
│   ├── Auth.php
│   ├── CronManager.php
│   ├── Dashboard.php
│   ├── Housekeeping.php
│   ├── Log.php
│   └── Mailer.php
│   └── TwigFactory.php
├── templates/
│   └── partials
├── public/
│   ├── index.php
│   ├── css/
├── data/
│   ├── crontab.txt
│   ├── cron_tasks.log
│   └── sqlite.db
├── scripts/
│   ├── init.php
│   └── rotate_log.php
```

---

## 📜 License

MIT License

> This project is open source and permissively licensed. Feel free to use, adapt, or extend it.

```
MIT License

Copyright (c) 2025 Chris Faber

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do so.
```

---

## 👤 Credits & Acknowledgments

Developed by **Chris Faber**.
Guidance and architectural assistance provided via ChatGPT (OpenAI).

> If you found this tool helpful, feel free to fork, star, or share it.

---
