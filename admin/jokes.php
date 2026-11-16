<?php
declare(strict_types=1);

/**
 * Admin jokes management entrypoint.
 * Routes requests for listing and managing jokes to `/admin/jokes`.
 */

$jokeRoute = '/admin/jokes'; // Target the admin jokes management route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
