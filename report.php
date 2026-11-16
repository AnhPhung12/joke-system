<?php
declare(strict_types=1);

/**
 * Content report submission entry point.
 *
 * Routes POST /reports requests through includes/dispatch.php
 * to App\Controllers\CommunityController::report().
 */

// Target virtual route path for the central router.
$jokeRoute = '/reports';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

