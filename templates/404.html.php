<?php
/**
 * 404 Not Found Error Template
 *
 * Rendered by the router/error handler when no route matches the requested path
 * or a requested resource (e.g. joke, profile) cannot be found.
 * Uses `e()` for XSS-safe URL output.
 */
?>
<!-- 404 error container with mascot and home return link -->
<div class="error-page">
    <div class="mascot small">•︵•</div>
    <h1>404 · Not found</h1>
    <p>The page you requested is not available.</p>
    <!-- Escape home URL to prevent XSS -->
    <a class="btn primary" href="<?= e(\App\Core\Url::to('/')) ?>">Back to Home</a>
</div>
