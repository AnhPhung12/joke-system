<?php
declare(strict_types=1);

/**
 * Moderator joke action entrypoint.
 * Routes joke moderation requests (using `{id}` from query/path params)
 * to `/moderator/jokes/{id}` in the central dispatcher.
 */

$jokeRoute = '/moderator/jokes/{id}'; // Target the joke moderation route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
