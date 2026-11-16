<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Application home page.
 *
 * Routes GET / through includes/dispatch.php to
 * App\Controllers\DashboardController::home().
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/'; // Assign the virtual route pattern for the application home page.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
