<?php
declare(strict_types=1);

/**
 * User registration entry point.
 *
 * Routes GET and POST /register requests through includes/dispatch.php
 * to App\Controllers\AuthController::registerForm() and ::register().
 */

// Target virtual route path for the central router.
$jokeRoute = '/register';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

