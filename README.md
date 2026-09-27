<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Setup

### Fresh clone — do this to avoid errors

`.env`, `vendor/`, `node_modules/`, and `public/build/` are **not** in Git, and the database must be created manually, so a fresh clone will break without these steps:

```powershell
# 0. Confirm your tools exist (missing any = "not recognized" errors later)
php --version
composer --version
node --version

# 0b. Confirm the MySQL extension is enabled (required or migrate fails)
php -m | findstr pdo_mysql

# 1. PHP dependencies (vendor/ is not cloned)
composer install

# 2. .env is not cloned — copy it, or you get "Missing APP_KEY" errors
Copy-Item .env.example .env
php artisan key:generate

# 3. Create the database in XAMPP's MySQL (or in phpMyAdmin):
#    CREATE DATABASE bscp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS bscp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Build tables + demo accounts (use migrate:fresh --seed to RESET a DB)
php artisan migrate --seed

# 5. JS dependencies + built assets (public/build/ is not cloned)
npm install
npm run build
```

> XAMPP must be running (MySQL on port 3306) before `php artisan migrate` or `php artisan serve`.

> Shortcut: `composer setup` runs steps 1, 2, 4, and 5 — but it does **not** create the database, so do step 3 first.

### Prerequisites

- PHP >= 8.2 (with `pdo_mysql` extension — included in XAMPP)
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) (npm included)
- [XAMPP](https://www.apachefriends.org) — MySQL must be running (start it in the XAMPP Control Panel)

### Install

```bash
# 1. Install PHP dependencies
composer install

# 2. Create your .env file and generate an app key
cp .env.example .env
php artisan key:generate

# 3. Create the database in MySQL (XAMPP), then migrate
#    cmd.exe: C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS bscp"
#    or make it in phpMyAdmin
php artisan migrate

# 4. Install JS dependencies and build assets
npm install
npm run build
```

> Or run everything at once with `composer setup`.

### Run the dev server

```bash
composer dev
```

This starts the server, queue worker, logs, and Vite together (via `concurrently`).

Alternatively, run them separately:

```bash
php artisan serve     # backend at http://localhost:8000
npm run dev           # Vite asset server
```

### Common artisan commands

```bash
php artisan serve              # start the dev server
php artisan migrate            # run pending migrations
php artisan migrate:fresh      # drop all tables and re-run migrations
php artisan migrate:rollback   # roll back the last batch
php artisan db:seed            # seed the database
php artisan migrate:fresh --seed   # fresh DB + seed in one go

php artisan make:model Post -m  # create a model + migration
php artisan make:controller Api/PostController -A  # controller + resource routes
php artisan make:request StorePostRequest          # form request
php artisan make:resource PostResource             # API resource
php artisan make:middleware EnsureTokenIsValid      # middleware

php artisan tinker             # REPL shell for the app
php artisan route:list         # list all routes
php artisan config:clear       # clear config cache
php artisan cache:clear        # clear cache
php artisan optimize:clear     # clear all caches
php artisan queue:work         # run the queue worker
php artisan test               # run tests
php artisan pint               # format code (Laravel Pint)
```

### Windows CMD / PowerShell

```powershell
# Create the MySQL database (cmd.exe or PowerShell)
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS bscp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Copy the env file (cmd.exe)
copy .env.example .env

# Run composer's setup script (does install + env + key + migrate + npm)
composer setup

# Run all dev processes at once (server, queue, logs, vite)
composer dev

# If scripts are blocked in PowerShell, allow them for the session
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

## Features

### Resident Module

The Resident Module allows citizens to:

- Register accounts and verify their details via email
- Manage personal and household profiles
- Request barangay documents
- Submit complaints or feedback
- Access public announcements

During crises, residents can:

- View emergency contacts
- Monitor calamity updates
- Locate nearby evacuation centers
- Submit immediate disaster assistance requests

### Barangay Staff and Admin Module

The Barangay Staff and Admin Module provides local officials with a central dashboard to oversee community activities.

**Staff** can:

- Manage household records
- Verify resident registration
- Process document requests
- Generate certificates
- Track complaint resolutions

**System administrators** hold higher-level control to:

- Manage user roles
- Manage system settings and activity logs
- Publish public advisories or disaster evacuation protocols

### AI-Enhanced Intelligent Features

To strengthen the system's computational component, the platform incorporates the following AI/NLP-based intelligent features across complaint and incident handling, information validation, and community analytics:

**Intelligent Complaint and Incident Classification**

- Uses Natural Language Processing (NLP) to analyze resident complaints, feedback, and emergency reports
- Automatically recommends a category (e.g., Fire/Emergency, Sanitation, Peace and Order) and an initial priority (Low, Medium, High)
- Generates a short summary of lengthy submissions for quicker staff review
- Barangay staff always confirm or override the AI's recommendation before action is taken

**Information Validation and Traceability**

- Checks submitted requests and complaints for completeness and consistency
- Flags potential duplicate reports or anomalies for staff verification
- Records both the AI's recommendation and the staff's final decision for transparency and accountability

**Community Data Analytics**

- Aggregates processed requests, complaints, and incident reports into a dashboard
- Shows trends by category, location/purok, date, and response time (e.g., average response and resolution time)
- Helps barangay officials identify recurring issues and plan resource allocation

## System Flow

The system operates as an interactive digital hub connecting residents, staff, and administrators through role-based access control. The workflow operates through the following sequence:

1. **Submission**: Residents access the portal to register accounts, request official certificates, file complaints, provide feedback, or request emergency assistance.
2. **Processing**: The portal validates input and stores transactions directly into the MySQL database.
3. **Execution**: Barangay staff and administrators receive real-time updates on their centralized dashboard, allowing them to review requests, approve certificate generations, update resident records, and manage complaints.
4. **Communication**: The platform broadcasts community-wide announcements, emergency notices, and direct request updates back to citizens.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
