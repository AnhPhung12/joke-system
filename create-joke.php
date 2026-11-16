<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Create a new joke.
 *
 * Routes GET/POST /jokes/create through includes/dispatch.php to
 * App\Controllers\JokeController::create() (GET) or store() (POST).
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/jokes/create'; // Assign the virtual route pattern for joke creation.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
