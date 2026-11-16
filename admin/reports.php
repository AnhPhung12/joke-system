<?php
declare(strict_types=1);

/**
 * Admin reports entrypoint.
 * Routes requests for viewing and managing abuse reports to `/admin/reports`.
 */

$jokeRoute = '/admin/reports'; // Target the admin reports management route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
