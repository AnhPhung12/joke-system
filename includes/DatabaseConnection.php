<?php

declare(strict_types=1);

// Load the central autoloader and helper definitions
require_once __DIR__ . '/bootstrap.php';

// Import the core Database singleton
use App\Core\Database;

/**
 * DatabaseConnection — Legacy Wrapper for PDO Database Access.
 *
 * Purpose: Exposes `getConnection()` for legacy scripts that expect an
 *          instantiable connection wrapper instead of calling `Database::connect()`.
 * Connections: Bootstraps autoloading via `bootstrap.php` and delegates to
 *              `App\Core\Database::connect()`.
 */
final class DatabaseConnection
{
    /**
     * Return the shared PDO database connection instance.
     */
    public function getConnection(): \PDO
    {
        // Return the shared PDO instance from App\Core\Database
        return Database::connect();
    }
}
