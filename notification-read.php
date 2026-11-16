<?php
declare(strict_types=1);

/**
 * Mark single notification as read entry point.
 *
 * Routes POST /notifications/{id}/read requests through includes/dispatch.php
 * to App\Controllers\ProfileController::read().
 */

// Target virtual route path (dispatcher substitutes {id} from $_GET['id']).
$jokeRoute = '/notifications/{id}/read';

// Hand off request handling to the shared front-controller dispatcher.
require __DIR__ . '/includes/dispatch.php';

