<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: View a single joke detail page.
 *
 * Routes GET /jokes/{id} through includes/dispatch.php to
 * App\Controllers\JokeController::show().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/jokes/{id}'; // Assign the virtual route pattern for viewing a single joke.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
