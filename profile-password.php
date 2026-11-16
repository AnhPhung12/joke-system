<?php
declare(strict_types=1);

/**
 * Profile password update entry point.
 *
 * Routes POST /profile/password requests through includes/dispatch.php
 * to App\Controllers\ProfileController::password().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile/password';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

