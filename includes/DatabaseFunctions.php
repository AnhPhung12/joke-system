<?php

declare(strict_types=1);

// Load the central autoloader and helper definitions
require_once __DIR__ . '/bootstrap.php';

// Import the Joke model
use App\Models\Joke;

/**
 * DatabaseFunctions — Legacy Read-Helper Facade for Jokes and Categories.
 *
 * Purpose: Provides simple object methods for legacy templates/scripts to fetch
 *          published jokes and active categories via `App\Models\Joke`.
 * Connections: Bootstraps autoloading via `bootstrap.php` and delegates to
 *              `App\Models\Joke`.
 */
final class DatabaseFunctions
{
    /**
     * Fetch the first page of newest published jokes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllJokes(): array
    {
        // Query page 1 of newest published jokes via the Joke model
        return (new Joke())->search('', 0, 'newest', 1);
    }

    /**
     * Fetch all active joke categories ordered by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        // Retrieve all active categories from the Joke model
        return (new Joke())->categories();
    }
}
