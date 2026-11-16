<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Edit an existing comment.
 *
 * Routes POST /comments/{id}/edit through includes/dispatch.php to
 * App\Controllers\CommunityController::editComment().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/comments/{id}/edit'; // Assign the virtual route pattern for comment editing.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
