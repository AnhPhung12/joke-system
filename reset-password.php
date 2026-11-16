<?php
declare(strict_types=1);

/**
 * Password reset entry point.
 *
 * Routes GET and POST /reset-password requests through includes/dispatch.php
 * to App\Controllers\PasswordResetController::resetForm() and ::resetPassword().
 */

// Target virtual route path for the central router.
$jokeRoute = '/reset-password';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

