<?php
declare(strict_types=1);

/**
 * Moderator report action entrypoint.
 * Routes report resolution requests (using `{id}` from query/path params)
 * to `/moderator/reports/{id}` in the central dispatcher.
 */

$jokeRoute = '/moderator/reports/{id}'; // Target the report moderation route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
