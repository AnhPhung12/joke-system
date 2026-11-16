<?php
/**
 * 403 Access Denied Error Template
 *
 * Rendered when an authenticated or guest user lacks the required role/permission
 * to view a protected route (e.g. admin or moderator endpoints).
 * Uses `e()` for XSS-safe URL output.
 */
?>
<!-- 403 error container with mascot and dashboard return link -->
<div class="error-page">
    <div class="mascot small">•︵•</div>
    <h1>403 · Access denied</h1>
    <p>You do not have permission to access this page.</p>
    <!-- Escape dashboard URL to prevent XSS -->
    <a class="btn primary" href="<?= e(\App\Core\Url::to('/dashboard')) ?>">Back to Dashboard</a>
</div>
