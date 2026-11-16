<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Delete an existing comment.
 *
 * Routes POST /comments/{id}/delete through includes/dispatch.php to
 * App\Controllers\CommunityController::deleteComment().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/comments/{id}/delete'; // Assign the virtual route pattern for comment deletion.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
