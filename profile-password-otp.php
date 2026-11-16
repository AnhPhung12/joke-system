<?php
declare(strict_types=1);

/**
 * Profile password-change OTP request entry point.
 *
 * Routes POST /profile/password/otp requests through includes/dispatch.php
 * to App\Controllers\ProfileController::passwordOtp().
 */

// Target virtual route path for the central router.
$jokeRoute = '/profile/password/otp';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

