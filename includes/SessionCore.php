<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session — Secure PHP session initialization and one-time flash message helper.
 *
 * Purpose:
 *   Configures hardened session cookie parameters (HttpOnly, SameSite=Lax, conditional Secure)
 *   and manages transient flash messages across redirects.
 * Connections:
 *   - Loaded as SessionCore.php via bootstrap.php.
 *   - Used by controllers and templates for user feedback banners.
 * OOP:
 *   - Final static utility class.
 * Data Structures & Algorithms:
 *   - Flash storage: Single-slot $_SESSION['flash'] string consumed atomically on read.
 */
final class Session
{
    /**
     * Start the PHP session with secure cookie settings if not already active.
     */
    public static function start(): void
    {
        // Skip initialization if a PHP session is already active.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set a dedicated application session cookie name.
        session_name('JOKE_SESSION');
        // Configure hardened session cookie flags for security.
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // Start or resume the PHP session.
        session_start();
    }

    /**
     * Store a one-time flash message in the session.
     */
    public static function flash(string $message): void
    {
        // Save the flash message in the session slot for the next request.
        $_SESSION['flash'] = $message;
    }

    /**
     * Retrieve and immediately clear the current flash message, if any.
     */
    public static function consumeFlash(): ?string
    {
        // Read the pending flash message or null if none exists.
        $value = $_SESSION['flash'] ?? null;
        // Remove the flash message so it is only displayed once.
        unset($_SESSION['flash']);

        // Return the consumed flash message.
        return $value;
    }
}
