<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: Forgot password and OTP request page.
 *
 * Routes GET/POST /forgot-password through includes/dispatch.php to
 * App\Controllers\PasswordResetController::forgotForm() (GET) or requestOtp() (POST).
 */

// Declare the virtual route path consumed by the front-controller dispatcher.
$jokeRoute = '/forgot-password'; // Assign the virtual route pattern for password reset requests.

// Hand off request bootstrapping, routing, and controller execution to dispatch.php.
require __DIR__ . '/includes/dispatch.php'; // Execute the front-controller dispatcher for this route.
