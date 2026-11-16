<?php
declare(strict_types=1);

/**
 * User's own jokes entry point.
 *
 * Routes GET /my-jokes requests through includes/dispatch.php
 * to App\Controllers\JokeController::mine().
 */

// Target virtual route path for the central router.
$jokeRoute = '/my-jokes';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

