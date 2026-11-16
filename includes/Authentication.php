<?php

declare(strict_types=1);

// Load the central autoloader and helper definitions
require_once __DIR__ . '/bootstrap.php';

// Import the core Auth class
use App\Core\Auth;

/**
 * Authentication — Legacy Wrapper for Session Authentication Checks.
 *
 * Purpose: Provides an object-oriented facade over `App\Core\Auth` for legacy
 *          entry points that instantiate `Authentication`.
 * Connections: Bootstraps autoloading via `bootstrap.php` and delegates to
 *              `App\Core\Auth`.
 * OOP: Final wrapper class delegating to static `Auth` methods.
 */
final class Authentication
{
    /**
     * Return the currently authenticated user array or null.
     *
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        // Delegate to the static Auth::user() session lookup
        return Auth::user();
    }

    /**
     * Enforce an active login session, redirecting to `/login` if unauthenticated.
     */
    public function requireLogin(): void
    {
        // Delegate to Auth::requireLogin() to redirect guests
        Auth::requireLogin();
    }
}
