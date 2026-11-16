<?php
declare(strict_types=1);

/**
 * Logout request entry point.
 *
 * Routes POST /logout requests through includes/dispatch.php
 * to App\Controllers\AuthController::logout().
 */

// Target virtual route path for the central router.
$jokeRoute = '/logout';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

