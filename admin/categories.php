<?php
declare(strict_types=1);

/**
 * Admin categories entrypoint.
 * Routes requests for category listing and management to the `/admin/categories`
 * handler in the central dispatcher.
 */

$jokeRoute = '/admin/categories'; // Target the admin categories route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
