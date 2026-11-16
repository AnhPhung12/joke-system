<?php
declare(strict_types=1);

/**
 * AI joke playground entry point.
 *
 * Routes GET /playground requests through includes/dispatch.php
 * to App\Controllers\GameController::index().
 */

// Target virtual route path for the central router.
$jokeRoute = '/playground';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

