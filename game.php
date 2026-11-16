<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: View a specific playground game session.
 *
 * Routes GET /playground/{id} through includes/dispatch.php to
 * App\Controllers\GameController::show().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/playground/{id}'; // Assign the virtual route pattern for viewing a playground game.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
