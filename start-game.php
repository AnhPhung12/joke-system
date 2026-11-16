<?php
declare(strict_types=1);

/**
 * AI joke playground session start entry point.
 *
 * Routes POST /playground/start requests through includes/dispatch.php
 * to App\Controllers\GameController::start().
 */

// Target virtual route path for the central router.
$jokeRoute = '/playground/start';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

