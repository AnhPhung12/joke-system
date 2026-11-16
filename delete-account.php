<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Delete the authenticated user's account.
 *
 * Routes POST /profile/delete through includes/dispatch.php to
 * App\Controllers\ProfileController::delete().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/profile/delete'; // Assign the virtual route pattern for account deletion.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
