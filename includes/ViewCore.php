<?php

declare(strict_types=1);

namespace App\Core;

/**
 * View — Template registry and layout renderer for MVC views.
 *
 * Purpose:
 *   Maps logical view names (e.g. 'jokes/index') to allowlisted PHP template files,
 *   extracts view data safely with EXTR_SKIP, and renders inside templates/layout.html.php.
 * Connections:
 *   - Loaded as ViewCore.php via bootstrap.php.
 *   - Invoked by all controllers and RouterCore for error pages.
 * OOP:
 *   - Final static utility class.
 * Data Structures & Algorithms:
 *   - Allowlist Map: O(1) lookup table preventing arbitrary file inclusion.
 */
final class View
{
    /**
     * Render a registered view template wrapped in the shared application layout.
     */
    public static function render(string $view, array $data = []): void
    {
        // Extract view variables into local scope without overwriting existing variables.
        extract($data, EXTR_SKIP);

        // Allowlist map from logical view names to physical template paths.
        $files = [
            'dashboard/home' => 'templates/home.html.php',
            'dashboard/user' => 'templates/dashboard.html.php',
            'dashboard/moderator' => 'templates/moderator-dashboard.html.php',
            'dashboard/admin' => 'templates/admin-dashboard.html.php',
            'auth/login' => 'templates/login.html.php',
            'auth/staff-login' => 'admin/templates/login.html.php',
            'auth/register' => 'templates/register.html.php',
            'auth/forgot-password' => 'templates/forgot-password.html.php',
            'auth/reset-password' => 'templates/reset-password.html.php',
            'jokes/index' => 'templates/jokes.html.php',
            'jokes/show' => 'templates/joke-detail.html.php',
            'jokes/form' => 'templates/joke-form.html.php',
            'jokes/mine' => 'templates/my-jokes.html.php',
            'games/index' => 'templates/playground.html.php',
            'games/show' => 'templates/game-session.html.php',
            'profile/index' => 'templates/profile.html.php',
            'profile/settings' => 'templates/profile-settings.html.php',
            'moderation/queue' => 'templates/moderation-queue.html.php',
            'admin/overview' => 'admin/templates/dashboard.html.php',
            'admin/staff-profile' => 'admin/templates/staff-profile.html.php',
            'admin/staff-settings' => 'admin/templates/staff-settings.html.php',
            'admin/users' => 'admin/templates/users.html.php',
            'admin/categories' => 'admin/templates/categories.html.php',
            'admin/settings' => 'admin/templates/settings.html.php',
            'admin/manage-jokes' => 'admin/templates/manage-jokes.html.php',
            'admin/reports' => 'admin/templates/reports.html.php',
            'errors/403' => 'templates/403.html.php',
            'errors/404' => 'templates/404.html.php',
        ];

        // Reject unregistered view names to prevent arbitrary template inclusion.
        if (!isset($files[$view])) {
            throw new \RuntimeException('Unknown template: ' . $view);
        }

        // Resolve the absolute path to the content template file.
        $contentFile = dirname(__DIR__) . '/' . $files[$view];
        // Verify that the mapped template file exists on disk.
        if (!is_file($contentFile)) {
            throw new \RuntimeException('Missing template: ' . $view);
        }

        // Expose the logical view name to the layout template.
        $viewName = $view;
        // Render the shared application layout, which includes $contentFile.
        require dirname(__DIR__) . '/templates/layout.html.php';
    }
}
