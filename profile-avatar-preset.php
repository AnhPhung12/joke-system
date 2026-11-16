<?php
declare(strict_types=1);

/**
 * Preset avatar selection entry point.
 *
 * Routes POST /profile/avatar/preset requests through includes/dispatch.php
 * to App\Controllers\ProfileController::presetAvatar().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile/avatar/preset';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

