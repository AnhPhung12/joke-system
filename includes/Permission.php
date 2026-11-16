<?php

declare(strict_types=1);

// Load core bootstrap dependencies and autoloaded classes.
require_once __DIR__ . '/bootstrap.php';

// Import the Auth helper for permission verification.
use App\Core\Auth;

/**
 * Permission — Legacy global-namespace facade for RBAC permission checks.
 *
 * Purpose:
 *   Provides an object-oriented wrapper around App\Core\Auth::can() and Auth::requirePermission()
 *   for legacy entrypoints outside the App namespace.
 * Connections:
 *   Loads bootstrap.php and delegates all authorization checks to App\Core\Auth.
 * OOP:
 *   Final adapter/facade class in the global namespace.
 * Data Structures & Algorithms:
 *   Direct delegation to Auth's role/permission lookup.
 */
final class Permission
{
    /**
     * Check whether the currently authenticated user has the given permission.
     */
    public function can(string $permission): bool
    {
        // Delegate permission check to the central Auth class.
        return Auth::can($permission);
    }

    /**
     * Enforce the given permission, aborting with 403/redirect if unauthorized.
     */
    public function require(string $permission): void
    {
        // Enforce permission via Auth, aborting if the user lacks access.
        Auth::requirePermission($permission);
    }
}
