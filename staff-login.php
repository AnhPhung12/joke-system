<?php
declare(strict_types=1);

/**
 * Staff login entry point.
 *
 * Routes GET and POST /staff/login requests through includes/dispatch.php
 * to App\Controllers\AuthController::staffLoginForm() and ::staffLogin().
 */

// Target virtual route path for the central router.
$jokeRoute = '/staff/login';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

