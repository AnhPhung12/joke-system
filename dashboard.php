<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: User dashboard page.
 *
 * Routes GET /dashboard through includes/dispatch.php to
 * App\Controllers\DashboardController::user().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/dashboard'; // Assign the virtual route pattern for the user dashboard.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
