<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Auth — Session Authentication & Role-Based Access Control (RBAC).
 *
 * Purpose: Manages authenticated user state, session lifecycle, role checks,
 *          and granular permission evaluation with per-account overrides.
 * Connections: Queries `App\Models\User` and `staff_permissions` via `Database::connect()`;
 *              uses `Session`, `Url`, and `View` for redirects and 403 error responses.
 * Data Structures & Security:
 *   - Uses a per-request static memoization cache (`$cached`) to avoid redundant DB queries.
 *   - Enforces `status === 'active'` on every request (auto-logs out suspended users).
 *   - Regenerates session ID on login (`session_regenerate_id(true)`) to prevent fixation.
 */
final class Auth
{
    /** @var array<string, mixed>|null Per-request memoized user record. */
    private static ?array $cached = null;

    /**
     * Return the currently authenticated active user, or null if guest/suspended.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        // Return memoized user record if already resolved during this request
        if (self::$cached !== null) {
            return self::$cached;
        }

        // Read authenticated user ID from PHP session
        $id = $_SESSION['user_id'] ?? null;
        // Return null immediately for guest visitors
        if (!$id) {
            return null;
        }

        // Fetch fresh user profile and roles from the database
        $user = (new User())->findById((int) $id);
        // Auto-logout if the account was deleted or suspended
        if (!$user || $user['status'] !== 'active') {
            self::logout();
            return null;
        }

        // Cache active user for subsequent calls in the same request
        self::$cached = $user;
        return $user;
    }

    /**
     * Establish an authenticated session for the given user record.
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user): void
    {
        // Rotate session ID and CSRF token to mitigate session fixation
        session_regenerate_id(true);
        // Store the authenticated user's primary key in session
        $_SESSION['user_id'] = (int) $user['id'];
        // Force regeneration of CSRF token on next form render
        unset($_SESSION['csrf_token']);
        // Clear request-level user cache
        self::$cached = null;
    }

    /**
     * Clear session state, expire the session cookie, and destroy the session.
     */
    public static function logout(): void
    {
        // Clear memoized user cache
        self::$cached = null;
        // Empty the session superglobal array
        $_SESSION = [];

        // Expire the session cookie in the browser if cookies are enabled
        if (ini_get('session.use_cookies')) {
            // Retrieve current cookie path, domain, and security flags
            $p = session_get_cookie_params();
            // Set cookie expiration in the past to delete it
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }

        // Destroy the server-side session storage
        session_destroy();
    }

    /**
     * Check whether the current user holds the specified role.
     */
    public static function hasRole(string $role): bool
    {
        // Resolve the current authenticated user
        $user = self::user();
        // Verify user exists and holds the requested role
        return $user !== null && in_array($role, $user['roles'], true);
    }

    /**
     * Evaluate whether the current user is granted a given permission key.
     */
    public static function can(string $permission): bool
    {
        // Resolve the current authenticated user
        $user = self::user();
        // Deny all permissions for unauthenticated guests
        if (!$user) {
            return false;
        }

        // Administrators have unrestricted access
        if (in_array('admin', $user['roles'], true)) {
            return true;
        }

        // Base permissions for standard users, expanded for moderators
        $defaults = ['dashboard.view', 'joke.create', 'profile.view'];
        // Grant default moderation and admin-panel permissions to moderators
        if (in_array('moderator', $user['roles'], true)) {
            $defaults = array_merge($defaults, [
                'moderation.view',
                'moderation.review',
                'admin.view',
                'analytics.view',
                'jokes.manage',
                'reports.manage',
            ]);
        }

        // Determine baseline permission status from role defaults
        $allowed = in_array($permission, $defaults, true);

        // Per-account overrides. Missing migration fails closed for additional grants.
        try {
            // Query explicit permission override for this user
            $stmt = Database::connect()->prepare(
                'SELECT is_allowed FROM staff_permissions WHERE user_id=? AND permission_key=?'
            );
            // Execute with user ID and permission key
            $stmt->execute([(int) $user['id'], $permission]);
            // Fetch scalar override flag (false if no row exists)
            $override = $stmt->fetchColumn();

            // Apply explicit override when present
            if ($override !== false) {
                $allowed = (bool) $override;
            }
        } catch (\PDOException $e) {
            // Log missing table/migration errors while preserving default check
            error_log('JOKE permission migration required: ' . $e->getMessage());
        }

        // Return final permission decision
        return $allowed;
    }

    /**
     * Redirect unauthenticated visitors to the login page.
     */
    public static function requireLogin(): void
    {
        // Redirect guests with a flash prompt
        if (!self::user()) {
            Session::flash('Please sign in to continue.');
            // Normalize script path separators and inspect request URI for staff routes
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $isAdminReq = str_contains($script, '/admin/') || preg_match('#/(?:admin|moderator)(?:/|$)#', $uri);
            // Send staff-area requests to the staff login page and all others to user login
            Url::redirect($isAdminReq ? '/staff/login' : '/login');
        }
    }

    /**
     * Require authentication and a specific permission, or abort with HTTP 403.
     */
    public static function requirePermission(string $permission): void
    {
        // Ensure the visitor is signed in first
        self::requireLogin();

        // Render 403 Forbidden page and exit if permission is denied
        if (!self::can($permission)) {
            http_response_code(403);
            View::render('errors/403', ['title' => '403 · Access denied']);
            exit;
        }
    }
}
