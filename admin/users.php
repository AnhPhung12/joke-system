<?php
declare(strict_types=1);

/**
 * Admin users management entrypoint.
 * Dispatches POST requests with an `id` query parameter to `/admin/users/{id}`
 * for user updates, and all other requests to the `/admin/users` listing.
 */

// Route POST updates for a specific user ID separately from the user list view.
$jokeRoute = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_GET['id']))
    ? '/admin/users/{id}' // Target individual user update route for POST with id.
    : '/admin/users'; // Target user listing route otherwise.

require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
