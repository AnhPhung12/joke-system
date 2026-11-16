<?php
declare(strict_types=1);

/**
 * Random "surprise me" joke entry point.
 *
 * Routes GET /jokes/surprise requests through includes/dispatch.php
 * to App\Controllers\JokeController::surprise().
 */

// Target virtual route path for the central router.
$jokeRoute = '/jokes/surprise';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

