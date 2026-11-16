<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Csrf — Cross-Site Request Forgery (CSRF) Protection.
 *
 * Purpose: Generates a 64-character CSPRNG token (`random_bytes(32)`) per session
 *          and enforces constant-time validation (`hash_equals()`) on all
 *          state-changing POST requests.
 * Connections: Invoked by controllers before mutating state and by views to
 *              populate hidden `_csrf` form fields.
 */
final class Csrf
{
    /**
     * Return the active session's CSRF token, generating one if absent.
     */
    public static function token(): string
    {
        // Generate a 64-character cryptographic hex token if none exists in session
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Return the active session CSRF token
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a submitted token against the session token in constant time.
     */
    public static function valid(?string $token): bool
    {
        // Ensure token is a string and matches session token via timing-safe comparison
        return is_string($token) && hash_equals(self::token(), $token);
    }

    /**
     * Abort the request with HTTP 419 if the submitted `_csrf` token is invalid.
     */
    public static function enforce(): void
    {
        // Reject POST requests lacking a valid CSRF token
        if (!self::valid($_POST['_csrf'] ?? null)) {
            // Set HTTP 419 Page Expired status code
            http_response_code(419);
            // Terminate execution immediately with an error message
            exit('Invalid CSRF token.');
        }
    }
}
