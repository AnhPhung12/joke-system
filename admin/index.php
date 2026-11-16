<?php
declare(strict_types=1);

/**
 * Admin dashboard entrypoint.
 * Routes requests to the `/admin/overview` handler in the central dispatcher.
 */

$jokeRoute = '/admin/overview'; // Target the admin overview dashboard route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
