<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Post a comment on a joke.
 *
 * Routes POST /jokes/{id}/comments through includes/dispatch.php to
 * App\Controllers\CommunityController::comment().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/jokes/{id}/comments'; // Assign the virtual route pattern for comment submission.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
