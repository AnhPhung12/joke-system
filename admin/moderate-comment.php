<?php
declare(strict_types=1);

/**
 * Moderator comment action entrypoint.
 * Routes comment moderation requests (using `{id}` from query/path params)
 * to `/moderator/comments/{id}` in the central dispatcher.
 */

$jokeRoute = '/moderator/comments/{id}'; // Target the comment moderation route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
