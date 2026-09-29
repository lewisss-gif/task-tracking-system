# Hub PM System — Project Management System

A full-featured project management system for **Nairobi Innovators Hub**, built with PHP, MySQL, and Bootstrap 5.

## Features

- **Role-based access control** — Administrator, Project Manager, Team Member
- **Dashboard** — Statistics, recent projects, upcoming deadlines, activity feed
- **Project management** — Create, edit, delete projects with progress tracking
- **Task management** — Assign tasks, set priorities, update status, filter & search
- **Milestones** — Track project milestones with due dates
- **Notifications** — In-app notifications for assignments, deadlines, and updates
- **Reports & Analytics** — Charts, top performers, project progress (Chart.js)
- **Activity logs** — Full audit trail of user actions
- **Automated deadline checker** — Cron job script for overdue tasks and milestone alerts

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8+ (PDO) |
| Database | MySQL / MariaDB |
| Frontend | Bootstrap 5, Chart.js |
| Server | XAMPP / Apache |

## Installation

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (or WAMP/MAMP)
- Web browser

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/lewisss-gif/task-tracking-system.git
   cd task-tracking-system
   ```

2. **Copy to htdocs**
   ```bash
   # Move the folder to your XAMPP htdocs directory
   # Default: C:\xampp\htdocs\task-tracking-system
   ```

3. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL**

4. **Create the database**
   - Go to http://localhost/phpmyadmin
   - Click **New** → Database name: `hub_pm_system` → **Create**
   - Select the database → **Import** → Choose `database/schema.sql` → **Go**

5. **Configure database connection** (if needed)
   - Open `config/database.php`
   - Update host, username, and password for your MySQL setup

6. **Run the app**
   - Open browser: `http://localhost/task-tracking-system/`

## Default Credentials

| Role | Username | Password |
|------|----------|----------|
| Administrator | `admin` | `admin123` |
| Project Manager | `john.manager` | `password123` |
| Team Member | `sarah.dev` | `password123` |
| Team Member | `mike.dev` | `password123` |
| Team Member | `lisa.dev` | `password123` |

> **Important:** Change the default admin password after first login.

## Project Structure

```
task-tracking-system/
├── api/
│   └── get_milestones.php      # AJAX endpoint for milestones
├── assets/
│   └── css/
│       └── style.css           # Custom styles
├── config/
│   ├── database.php            # PDO database connection
│   └── constants.php           # Application constants
├── cron/
│   └── deadline_checker.php    # Automated deadline checker
├── includes/
│   ├── auth.php                # Authentication & session management
│   ├── functions.php           # Helper functions
│   └── navbar.php              # Navigation bar
├── database/
│   └── schema.sql              # Database schema + sample data
├── dashboard.php               # Main dashboard
├── login.php                   # Login page
├── logout.php                  # Logout handler
├── notifications.php           # Notifications page
├── projects.php                # Projects management
├── reports.php                 # Reports & analytics
├── tasks.php                   # Tasks management
├── index.php                   # Entry point
└── README.md                   # This file
```

## Cron Job Setup

### Linux/Mac
```bash
0 8 * * * php /path/to/cron/deadline_checker.php
```

### Windows
Use Task Scheduler to run `cron/deadline_checker.php` daily at 8:00 AM.

## License

MIT License — Nairobi Innovators Hub
