<?php
declare(strict_types=1);

/**
 * User profile entry point.
 *
 * Routes GET and POST /profile requests through includes/dispatch.php
 * to App\Controllers\ProfileController::index() and ::update().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

