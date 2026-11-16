<?php
declare(strict_types=1);

/**
 * Admin settings entrypoint.
 * Routes requests for system/admin configuration to `/admin/settings`.
 */

$jokeRoute = '/admin/settings'; // Target the admin settings route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
