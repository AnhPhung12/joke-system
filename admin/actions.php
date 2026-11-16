<?php
declare(strict_types=1);

/**
 * Dedicated POST-only entrypoint for administrative actions.
 * Maps the `action` query parameter to an `/admin/*` route and forwards
 * execution to the central dispatcher in `includes/dispatch.php`.
 */

// Reject non-POST requests immediately to enforce mutating-action semantics.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405); // Send HTTP 405 Method Not Allowed status.
    exit('Method not allowed'); // Terminate execution with an error response.
}

// Resolve the target route from the query string, mapping the legacy alias.
$action = (string) ($_GET['action'] ?? ''); // Extract the action query parameter as a string.
$jokeRoute = $action === 'create-staff' ? '/admin/staff/create' : $action; // Map legacy 'create-staff' alias to its canonical route.

// Restrict this entrypoint strictly to `/admin/` routes.
if (!str_starts_with($jokeRoute, '/admin/')) {
    http_response_code(404); // Send HTTP 404 Not Found status.
    exit('Not found'); // Terminate execution for non-admin routes.
}

// Hand off request handling to the central router/dispatcher.
require dirname(__DIR__) . '/includes/dispatch.php'; // Load and execute the central request dispatcher.
