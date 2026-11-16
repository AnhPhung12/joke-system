<?php
declare(strict_types=1);

/**
 * Mark all notifications as read entry point.
 *
 * Routes POST /notifications/read-all requests through includes/dispatch.php
 * to App\Controllers\ProfileController::readAll().
 */

// Target virtual route path for the central router.
$jokeRoute = '/notifications/read-all';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

