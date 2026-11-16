<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Toggle like status on a joke.
 *
 * Routes POST /jokes/{id}/like through includes/dispatch.php to
 * App\Controllers\JokeController::toggle($id, 'likes').
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/jokes/{id}/like'; // Assign the virtual route pattern for toggling joke likes.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
