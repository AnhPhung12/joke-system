<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Submit a turn in a playground game session.
 *
 * Routes POST /playground/{id}/turn through includes/dispatch.php to
 * App\Controllers\GameController::turn().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/playground/{id}/turn'; // Assign the virtual route pattern for playground turn submission.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
