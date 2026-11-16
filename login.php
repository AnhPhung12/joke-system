<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: User login page and form submission.
 *
 * Routes GET/POST /login through includes/dispatch.php to
 * App\Controllers\AuthController::loginForm() (GET) or login() (POST).
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/login'; // Assign the virtual route pattern for user login.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
