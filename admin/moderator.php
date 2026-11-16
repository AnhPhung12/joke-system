<?php
declare(strict_types=1);

/**
 * Moderator queue entrypoint.
 * Routes requests for the moderation queue view to `/moderator/queue`.
 */

$jokeRoute = '/moderator/queue'; // Target the moderator queue route.
require dirname(__DIR__) . '/includes/dispatch.php'; // Hand off execution to the central dispatcher.
