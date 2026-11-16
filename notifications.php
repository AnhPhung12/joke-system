<?php
declare(strict_types=1);

/**
 * Notifications list entry point.
 *
 * Routes GET /notifications requests through includes/dispatch.php
 * to App\Controllers\ProfileController::notifications().
 */

// Target virtual route path for the central router.
$jokeRoute = '/notifications';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

