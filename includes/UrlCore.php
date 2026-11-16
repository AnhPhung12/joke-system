<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Url — Application URL resolver, route-to-PHP-entrypoint mapper, and HTTP redirect helper.
 *
 * Purpose:
 *   Computes the application's base directory (handling /admin entrypoints), maps clean logical
 *   routes (e.g. /jokes/5/edit) to physical PHP scripts with query parameters, and issues 303 redirects.
 * Connections:
 *   - Loaded as UrlCore.php via bootstrap.php.
 *   - Used across all controllers, templates, and RouterCore.php.
 * OOP:
 *   - Final static utility class.
 * Data Structures & Algorithms:
 *   - Route Resolution: O(1) associative lookup for static routes, falling back to regex pattern
 *     matching that extracts numeric IDs into query parameters.
 */
final class Url
{
    /**
     * Determine the base URL path for the running script, stripping any trailing /admin segment.
     */
    public static function base(): string
    {
        // Normalize backslashes in SCRIPT_NAME to forward slashes for cross-platform paths.
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        // Extract the parent directory of the executing script.
        $dir = rtrim(dirname($script), '/');

        // Strip trailing /admin so admin scripts resolve URLs from the app root.
        if (str_ends_with($dir, '/admin')) {
            $dir = substr($dir, 0, -6);
        }

        // Return an empty string when running at the web root.
        return $dir === '.' ? '' : $dir;
    }

    /**
     * Resolve a logical application route (or /assets/* path) to its concrete URL.
     */
    public static function to(string $path = '/'): string
    {
        // Prefix static asset paths directly with the application base URL.
        if (str_starts_with($path, '/assets/')) {
            return self::base() . $path;
        }

        // Parse the input path into route path and query components.
        $parts = parse_url($path);
        // Normalize the route path with a single leading slash.
        $route = '/' . trim($parts['path'] ?? '/', '/');
        // Collapse double-slash root path into a single slash.
        if ($route === '//') {
            $route = '/';
        }

        // Parse any existing query string parameters into an associative array.
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        // Map static logical routes to their physical PHP entrypoint scripts.
        $simple = [
            '/' => '/index.php',
            '/login' => '/login.php',
            '/register' => '/register.php',
            '/logout' => '/logout.php',
            '/forgot-password' => '/forgot-password.php',
            '/reset-password' => '/reset-password.php',
            '/reset-password/verify' => '/verify-reset-otp.php',
            '/dashboard' => '/dashboard.php',
            '/jokes' => '/jokes.php',
            '/jokes/create' => '/create-joke.php',
            '/jokes/surprise' => '/surprise-joke.php',
            '/my-jokes' => '/my-jokes.php',
            '/profile' => '/profile.php',
            '/profile/settings' => '/profile-settings.php',
            '/profile/password' => '/profile-password.php',
            '/profile/password/otp' => '/profile-password-otp.php',
            '/profile/avatar' => '/profile-avatar.php',
            '/profile/avatar/preset' => '/profile-avatar-preset.php',
            '/profile/delete' => '/delete-account.php',
            '/staff/login' => '/staff-login.php',
            '/playground' => '/playground.php',
            '/playground/start' => '/start-game.php',
            '/notifications' => '/notifications.php',
            '/notifications/read-all' => '/notifications-read-all.php',
            '/reports' => '/report.php',
            '/moderator' => '/admin/moderator.php',
            '/moderator/queue' => '/admin/moderator.php',
            '/admin' => '/admin/index.php',
            '/admin/overview' => '/admin/index.php',
            '/admin/users' => '/admin/users.php',
            '/admin/categories' => '/admin/categories.php',
            '/admin/settings' => '/admin/settings.php',
            '/admin/jokes' => '/admin/jokes.php',
            '/admin/reports' => '/admin/reports.php',
            '/admin/staff/create' => '/admin/actions.php',
        ];

        // Attempt O(1) lookup in the static route table.
        $target = $simple[$route] ?? null;

        // Fall back to regex matching for parameterized routes with numeric IDs.
        if ($target === null) {
            // Map parameterized route patterns to their target PHP scripts.
            $patterns = [
                '~^/jokes/(\d+)$~' => '/joke.php',
                '~^/jokes/(\d+)/edit$~' => '/edit-joke.php',
                '~^/jokes/(\d+)/delete$~' => '/delete-joke.php',
                '~^/jokes/(\d+)/like$~' => '/like-joke.php',
                '~^/jokes/(\d+)/favorite$~' => '/favorite-joke.php',
                '~^/jokes/(\d+)/comments$~' => '/comment.php',
                '~^/comments/(\d+)/edit$~' => '/edit-comment.php',
                '~^/comments/(\d+)/delete$~' => '/delete-comment.php',
                '~^/moderator/jokes/(\d+)$~' => '/admin/moderate-joke.php',
                '~^/moderator/comments/(\d+)$~' => '/admin/moderate-comment.php',
                '~^/moderator/reports/(\d+)$~' => '/admin/moderate-report.php',
                '~^/admin/users/(\d+)$~' => '/admin/users.php',
                '~^/admin/users/(\d+)/delete$~' => '/admin/actions.php',
                '~^/admin/staff/(\d+)/update$~' => '/admin/actions.php',
                '~^/admin/staff/(\d+)/permissions$~' => '/admin/actions.php',
                '~^/admin/categories/(\d+)/delete$~' => '/admin/actions.php',
                '~^/admin/jokes/(\d+)/delete$~' => '/admin/actions.php',
                '~^/playground/(\d+)$~' => '/game.php',
                '~^/playground/(\d+)/turn$~' => '/game-turn.php',
                '~^/playground/(\d+)/delete$~' => '/delete-game.php',
                '~^/notifications/(\d+)/read$~' => '/notification-read.php',
            ];

            // Test each parameterized pattern until a match is found.
            foreach ($patterns as $regex => $file) {
                if (preg_match($regex, $route, $m)) {
                    // Assign the matched PHP entrypoint script.
                    $target = $file;
                    // Pass the captured numeric ID as a query parameter.
                    $query['id'] = $m[1];
                    // Include the original route path as 'action' for unified admin action handlers.
                    if ($target === '/admin/actions.php') {
                        $query['action'] = $route;
                    }
                    break;
                }
            }
        }

        // Fail loudly if the requested route is not registered.
        if ($target === null) {
            throw new \InvalidArgumentException('Unknown URL route: ' . $route);
        }

        // Attach the explicit action parameter for staff creation requests.
        if ($route === '/admin/staff/create') {
            $query['action'] = 'create-staff';
        }

        // Build and return the full URL with base prefix and encoded query string.
        return self::base() . $target . ($query ? '?' . http_build_query($query) : '');
    }

    /**
     * Send an HTTP 303 See Other redirect to the resolved route and terminate execution.
     */
    public static function redirect(string $path): never
    {
        // Emit a 303 See Other Location header pointing to the resolved route URL.
        header('Location: ' . self::to($path), true, 303);
        // Stop script execution immediately after sending the redirect header.
        exit;
    }
}
