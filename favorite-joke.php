<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Toggle favorite status on a joke.
 *
 * Routes POST /jokes/{id}/favorite through includes/dispatch.php to
 * App\Controllers\JokeController::toggle($id, 'favorites').
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/jokes/{id}/favorite'; // Assign the virtual route pattern for toggling joke favorites.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
