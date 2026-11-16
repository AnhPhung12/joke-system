<?php

/**
 * Custom avatar upload entry point.
 *
 * Routes POST /profile/avatar requests through includes/dispatch.php
 * to App\Controllers\ProfileController::avatar().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile/avatar';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

