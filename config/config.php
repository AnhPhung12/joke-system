<?php
/**
 * config.php — Application, Database, and SMTP Mail Configuration.
 *
 * Purpose: Centralizes environment-driven settings for the JOKÉ web application,
 *          returning an associative configuration array consumed by DatabaseCore
 *          and SmtpMailer.
 * Connections: Loaded via `require` in `includes/DatabaseCore.php` and
 *              `includes/SmtpMailer.php`.
 * Data Structures: Returns a nested associative array (`app`, `db`, `mail`)
 *                  with safe local development defaults via `getenv()` fallbacks.
 */
// Return the centralized associative configuration array for the application
return [
    // Core application metadata and debug toggle
    'app' => [
        // Public-facing application display name
        'name' => 'JOKÉ',
        // Disable verbose debug output by default
        'debug' => false,
    ],

    // MySQL / MariaDB connection parameters (overridable via environment variables)
    'db' => [
        // Database server hostname or IP address
        'host' => getenv('JOKE_DB_HOST') ?: '127.0.0.1',
        // Database server TCP port
        'port' => getenv('JOKE_DB_PORT') ?: '3306',
        // Target MySQL database schema name
        'name' => getenv('JOKE_DB_NAME') ?: 'joke_db',
        // Database authentication username
        'user' => getenv('JOKE_DB_USER') ?: 'root',
        // Database authentication password
        'pass' => getenv('JOKE_DB_PASS') ?: '',
    ],

    // Set these as environment variables in Apache/XAMPP and Render. Never commit a Gmail App Password.
    'mail' => [
        // Outgoing SMTP relay hostname
        'host' => getenv('JOKE_SMTP_HOST') ?: 'smtp.gmail.com',
        // Outgoing SMTP server port cast to integer
        'port' => (int) (getenv('JOKE_SMTP_PORT') ?: 587),
        // SMTP authentication username
        'username' => getenv('JOKE_SMTP_USERNAME') ?: '',
        // SMTP authentication password or app password
        'password' => getenv('JOKE_SMTP_PASSWORD') ?: '',
        // Sender email address shown in the From header
        'from_email' => getenv('JOKE_MAIL_FROM') ?: '',
        // Sender display name shown in the From header
        'from_name' => getenv('JOKE_MAIL_FROM_NAME') ?: 'JOKÉ',
    ],
];
