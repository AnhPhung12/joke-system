<?php
declare(strict_types=1);

/**
 * Profile settings page entry point.
 *
 * Routes GET /profile/settings requests through includes/dispatch.php
 * to App\Controllers\ProfileController::settings().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile/settings';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

