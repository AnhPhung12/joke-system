<?php
declare(strict_types=1);

/**
 * Password reset OTP verification entry point.
 *
 * Routes POST /reset-password/verify requests through includes/dispatch.php
 * to App\Controllers\PasswordResetController::verifyOtp().
 */

// Target virtual route path for the central router.
$jokeRoute = '/reset-password/verify';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

