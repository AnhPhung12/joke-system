<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Admin — Data-Access Model for Administrative Operations.
 *
 * Purpose: Encapsulates all database queries and transactional mutations required
 *          by the admin dashboard (statistics, user/staff management, RBAC
 *          permission overrides, categories, jokes, and system settings).
 * Connections: Instantiated by `App\Controllers\AdminController`; uses
 *              `App\Core\Database::connect()` for PDO access.
 * OOP: Final model class with a single injected/acquired `PDO` dependency.
 * Data Structures & Algorithms:
 *   - Uses `match()` expression whitelists for safe dynamic SQL `ORDER BY` clauses.
 *   - Wraps multi-table mutations (user role updates, hard deletes, staff edits)
 *     in ACID transactions with `FOR UPDATE` row locks and rollback guards.
 */
final class Admin
{
    // Shared PDO connection for administrative queries and transactions
    private PDO $db;

    /**
     * Granular permission keys assignable to moderator accounts.
     */
    public const STAFF_PERMISSIONS = [
        'analytics.view',
        'users.view',
        'users.delete',
        'categories.manage',
        'settings.manage',
        'jokes.manage',
        'reports.manage',
        'moderation.view',
        'moderation.review',
    ];

    /**
     * Initialize the model with the shared PDO database connection.
     */
    public function __construct()
    {
        // Acquire singleton PDO connection
        $this->db = Database::connect();
    }

    /**
     * Gather high-level entity counts for the admin overview dashboard.
     *
     * @return array<string, int> Counts keyed by entity name plus `pending` jokes.
     */
    public function stats(): array
    {
        // Initialize output array
        $out = [];

        // Count total rows across core platform tables (excluding anonymized deleted users)
        foreach (['users' => 'users', 'jokes' => 'jokes', 'comments' => 'comments', 'reports' => 'reports', 'games' => 'game_sessions'] as $key => $table) {
            $sqlCount = $key === 'users'
                ? "SELECT COUNT(*) FROM users WHERE status <> 'deleted' AND username NOT LIKE 'deleted\\_%'"
                : 'SELECT COUNT(*) FROM ' . $table;
            $out[$key] = (int) $this->db->query($sqlCount)->fetchColumn();
        }

        // Count jokes awaiting moderation review
        $out['pending'] = (int) $this->db->query("SELECT COUNT(*) FROM jokes WHERE status='pending'")->fetchColumn();

        return $out;
    }

    /**
     * Fetch daily joke submission counts over the past 30 days for growth charts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function growth(): array
    {
        // Aggregate daily joke creations over the trailing 30-day window
        return $this->db->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM jokes WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY day"
        )->fetchAll();
    }

    /**
     * Search, filter, and sort user accounts with aggregated roles and joke counts.
     *
     * @param string $q    Search term matched against username, email, or location.
     * @param string $role Optional role filter ('user', 'moderator', 'admin').
     * @param string $sort Sort key mapped via whitelist.
     * @param string $date Optional creation date filter (YYYY-MM-DD).
     * @return array<int, array<string, mixed>>
     */
    public function users(string $q = '', string $role = '', string $sort = 'newest', string $date = ''): array
    {
        // Base search predicate across username, email, and location (hide deleted/anonymized users)
        $where = [
            "u.status <> 'deleted'",
            "u.username NOT LIKE 'deleted\\_%'",
            '(u.username LIKE ? OR u.email LIKE ? OR u.location LIKE ?)',
        ];
        $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];

        // Normalize role filter alias ('client' -> 'user')
        $targetRole = match (strtolower(trim($role))) {
            'client', 'user' => 'user',
            'moderator' => 'moderator',
            'admin' => 'admin',
            default => '',
        };

        // Apply role membership subquery filter when a role is specified
        if ($targetRole !== '') {
            if ($targetRole === 'user') {
                // Regular users are those without elevated staff roles
                $where[] = 'u.id NOT IN (SELECT ur2.user_id FROM user_roles ur2 JOIN roles r2 ON r2.id=ur2.role_id WHERE r2.role_name IN ("admin","moderator"))';
            } else {
                // Filter to users assigned the requested staff role
                $where[] = 'u.id IN (SELECT ur2.user_id FROM user_roles ur2 JOIN roles r2 ON r2.id=ur2.role_id WHERE r2.role_name=?)';
                $params[] = $targetRole;
            }
        }

        // Optional exact registration date filter
        if ($date !== '') {
            $where[] = 'DATE(u.created_at) = ?';
            $params[] = $date;
        }

        // Whitelist-mapped ORDER BY clause to prevent SQL injection
        $orderBy = match ($sort) {
            'name_asc' => 'u.username ASC',
            'name_desc' => 'u.username DESC',
            'location_asc' => 'u.location ASC, u.id DESC',
            'location_desc' => 'u.location DESC, u.id DESC',
            'jokes_desc' => 'joke_count DESC, u.id DESC',
            'jokes_asc' => 'joke_count ASC, u.id ASC',
            'role_asc', 'role' => 'roles ASC, u.id DESC',
            'role_desc' => 'roles DESC, u.id DESC',
            'date_desc', 'newest' => 'u.created_at DESC, u.id DESC',
            'date_asc', 'oldest' => 'u.created_at ASC, u.id ASC',
            'time_asc', 'earliest' => 'TIME(u.created_at) ASC, u.id ASC',
            'time_desc', 'latest' => 'TIME(u.created_at) DESC, u.id DESC',
            default => 'u.created_at DESC, u.id DESC',
        };

        // Query users with aggregated role names and non-deleted joke counts
        $sql = 'SELECT u.id,u.username,u.email,u.location,u.avatar_path,(u.avatar_data IS NOT NULL) AS has_avatar,u.status,u.created_at,COALESCE(NULLIF(GROUP_CONCAT(DISTINCT r.role_name ORDER BY r.role_name SEPARATOR ", "),""),"user") roles,COUNT(DISTINCT j.id) AS joke_count FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id LEFT JOIN jokes j ON j.user_id=u.id AND j.status<>"deleted" WHERE ' . implode(' AND ', $where) . ' GROUP BY u.id ORDER BY ' . $orderBy . ' LIMIT 100';

        // Prepare, execute, and return matching user rows
        $s = $this->db->prepare($sql);
        $s->execute($params);

        return $s->fetchAll();
    }

    /**
     * Update a user's status and optionally reassign their role inside a transaction.
     *
     * @param int    $target Target user ID.
     * @param int    $actor  Acting administrator user ID.
     * @param string $role   New role name (empty string keeps existing roles).
     * @param string $status New status ('active' or 'suspended').
     * @return bool True on success, false if validation or safety checks fail.
     */
    public function setUser(int $target, int $actor, string $role, string $status): bool
    {
        // Reject invalid status values
        if (!in_array($status, ['active', 'suspended'], true)) {
            return false;
        }

        // Start transaction for atomic role/status update and audit logging
        $this->db->beginTransaction();

        try {
            // Lock target user row to prevent concurrent modifications
            $s = $this->db->prepare('SELECT id,status FROM users WHERE id=? FOR UPDATE');
            $s->execute([$target]);
            $u = $s->fetch();

            // Abort if target user does not exist
            if (!$u) {
                $this->db->rollBack();
                return false;
            }

            // Retrieve current roles for audit logging and last-admin protection
            $s = $this->db->prepare('SELECT r.role_name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?');
            $s->execute([$target]);
            $old = $s->fetchAll(PDO::FETCH_COLUMN);

            // Prevent admins from suspending themselves
            if ($target === $actor && $status !== 'active') {
                $this->db->rollBack();
                return false;
            }

            // Ensure at least one active administrator remains on the platform
            if (in_array('admin', $old, true) && $status !== 'active') {
                $count = (int) $this->db->query(
                    "SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.role_name='admin' AND u.status='active'"
                )->fetchColumn();

                if ($count <= 1) {
                    $this->db->rollBack();
                    return false;
                }
            }

            // Update account status
            $s = $this->db->prepare('UPDATE users SET status=? WHERE id=?');
            $s->execute([$status, $target]);

            // Replace role assignment if a new role was specified
            if ($role !== '') {
                $s = $this->db->prepare('DELETE FROM user_roles WHERE user_id=?');
                $s->execute([$target]);

                $s = $this->db->prepare('INSERT INTO user_roles(user_id,role_id,assigned_by) SELECT ?,id,? FROM roles WHERE role_name=?');
                $s->execute([$target, $actor, $role]);

                // Abort if the requested role name does not exist
                if ($s->rowCount() !== 1) {
                    throw new \RuntimeException('Missing role');
                }
            }

            // Record before/after state in the moderation audit log
            $log = $this->db->prepare(
                'INSERT INTO moderation_logs(actor_user_id,action,target_type,target_id,reason,before_data,after_data) VALUES(?,?,?,?,?,?,?)'
            );
            $log->execute([
                $actor,
                'user_role_status_update',
                'user',
                $target,
                'Admin management',
                json_encode(['roles' => $old, 'status' => $u['status']]),
                json_encode(['role' => $role ?: $old, 'status' => $status]),
            ]);

            // Commit transaction
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back on any error
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch per-user permission overrides as a `permission_key => is_allowed` map.
     *
     * @param int $id Moderator user ID.
     * @return array<string, int|bool>
     */
    public function staffPermissions(int $id): array
    {
        // Query explicit permission overrides for the specified moderator
        $s = $this->db->prepare('SELECT permission_key,is_allowed FROM staff_permissions WHERE user_id=?');
        $s->execute([$id]);

        // Return associative key-value pairs
        return $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Replace all permission overrides for a moderator account.
     *
     * @param int                  $id     Target moderator user ID.
     * @param array<string, mixed> $values Submitted checkbox values keyed by permission name.
     */
    public function savePermissions(int $id, array $values): void
    {
        // Verify target account holds the moderator role
        $s = $this->db->prepare("SELECT r.role_name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
        $s->execute([$id]);
        $roles = $s->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('moderator', $roles, true)) {
            throw new \RuntimeException('Only moderators can receive permission overrides.');
        }

        // Replace all permission overrides atomically
        $this->db->beginTransaction();

        try {
            // Clear existing overrides for this moderator
            $del = $this->db->prepare('DELETE FROM staff_permissions WHERE user_id=?');
            $del->execute([$id]);

            // Insert updated boolean flag for each known permission key
            $ins = $this->db->prepare('INSERT INTO staff_permissions(user_id,permission_key,is_allowed) VALUES(?,?,?)');
            foreach (self::STAFF_PERMISSIONS as $key) {
                $ins->execute([$id, $key, isset($values[$key]) ? 1 : 0]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a new moderator account with a hashed password and role assignment.
     *
     * @param string $username Alphanumeric/underscore username (3–40 chars).
     * @param string $email    Valid email address.
     * @param string $password Plaintext password (minimum 12 chars).
     * @param string $role     Must be 'moderator'.
     */
    public function createStaff(string $username, string $email, string $password, string $role): void
    {
        // Enforce moderator role, username format, valid email, and 12+ char password
        if (
            $role !== 'moderator'
            || !preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12
        ) {
            throw new \InvalidArgumentException('Only Moderator accounts can be created here.');
        }

        // Insert user and assign moderator role atomically
        $this->db->beginTransaction();

        try {
            // Create active user record with hashed password
            $s = $this->db->prepare('INSERT INTO users(username,email,password_hash,status) VALUES(?,?,?,"active")');
            $s->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $this->db->lastInsertId();

            // Attach moderator role
            $s = $this->db->prepare('INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE role_name=?');
            $s->execute([$id, $role]);

            if ($s->rowCount() !== 1) {
                throw new \RuntimeException('Role missing');
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing moderator account's profile, status, and optional password.
     *
     * @param int    $id       Target moderator user ID.
     * @param string $username Updated username.
     * @param string $email    Updated email address.
     * @param string $role     Must remain 'moderator'.
     * @param string $status   Account status ('active' or 'suspended').
     * @param string $password Optional new password (empty string leaves unchanged).
     * @param int    $actor    Acting administrator user ID.
     */
    public function updateStaff(int $id, string $username, string $email, string $role, string $status, string $password, int $actor): void
    {
        // Validate staff fields and optional 12+ character password
        if (
            $role !== 'moderator'
            || !in_array($status, ['active', 'suspended'], true)
            || !preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($password !== '' && strlen($password) < 12)
        ) {
            throw new \InvalidArgumentException('Only Moderator accounts can be edited here.');
        }

        // Prevent self-demotion or self-suspension
        if ($id === $actor && ($role !== 'admin' || $status !== 'active')) {
            throw new \RuntimeException('Cannot remove your own admin access.');
        }

        // Start transaction for locked role check and profile update
        $this->db->beginTransaction();

        try {
            // Lock and inspect existing roles
            $s = $this->db->prepare('SELECT role_name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? FOR UPDATE');
            $s->execute([$id]);
            $old = $s->fetchAll(PDO::FETCH_COLUMN);

            // Protect bootstrap admin and non-moderator accounts from editing here
            if (in_array('admin', $old, true)) {
                throw new \RuntimeException('The bootstrap administrator is managed outside the staff editor.');
            }

            if (!in_array('moderator', $old, true)) {
                throw new \RuntimeException('Regular users cannot be edited here.');
            }

            // Ensure last active admin cannot be removed
            if (in_array('admin', $old, true) && ($role !== 'admin' || $status !== 'active') && $this->activeAdmins() <= 1) {
                throw new \RuntimeException('Last active admin must remain.');
            }

            // Build dynamic UPDATE depending on whether a new password was provided
            $sql = 'UPDATE users SET username=?,email=?,status=?' . ($password !== '' ? ',password_hash=?' : '') . ' WHERE id=?';
            $args = [$username, $email, $status];
            if ($password !== '') {
                $args[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $args[] = $id;

            // Execute user profile update
            $s = $this->db->prepare($sql);
            $s->execute($args);

            // Re-sync role assignment
            $this->db->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
            $this->db->prepare('INSERT INTO user_roles(user_id,role_id,assigned_by) SELECT ?,id,? FROM roles WHERE role_name=?')->execute([$id, $actor, $role]);

            // Remove moderator overrides if promoted to admin
            if ($role === 'admin') {
                $this->db->prepare('DELETE FROM staff_permissions WHERE user_id=?')->execute([$id]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Count how many active administrator accounts currently exist.
     */
    private function activeAdmins(): int
    {
        // Count distinct active users holding the 'admin' role
        return (int) $this->db->query(
            "SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.role_name='admin' AND u.status='active'"
        )->fetchColumn();
    }

    /**
     * Permanently delete a user and all of their authored content, reports, and logs.
     *
     * @param int    $id       Target user ID.
     * @param int    $actor    Acting administrator user ID.
     * @param string $username Exact username confirmation string.
     */
    public function hardDelete(int $id, int $actor, string $username): void
    {
        // Prevent administrators from deleting their own account
        if ($id === $actor) {
            throw new \RuntimeException('You cannot delete your own account.');
        }

        // Begin transaction to cascade-delete user data cleanly
        $this->db->beginTransaction();

        try {
            // Lock target user and verify username confirmation in constant time
            $s = $this->db->prepare('SELECT username,avatar_path FROM users WHERE id=? FOR UPDATE');
            $s->execute([$id]);
            $u = $s->fetch();

            if (!$u || !hash_equals($u['username'], $username)) {
                throw new \RuntimeException('Username confirmation does not match.');
            }

            // Check target's roles to protect the last active admin
            $s = $this->db->prepare('SELECT r.role_name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?');
            $s->execute([$id]);
            $roles = $s->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('admin', $roles, true) && $this->activeAdmins() <= 1) {
                throw new \RuntimeException('Cannot delete the last active admin.');
            }

            // Remove reports pointing to content that is about to be removed (polymorphic targets).
            $this->db->prepare(
                "DELETE FROM reports WHERE target_type='comment' AND target_id IN (SELECT id FROM comments WHERE user_id=? OR joke_id IN (SELECT id FROM jokes WHERE user_id=?))"
            )->execute([$id, $id]);
            $this->db->prepare(
                "DELETE FROM reports WHERE target_type='joke' AND target_id IN (SELECT id FROM jokes WHERE user_id=?)"
            )->execute([$id]);

            // Remove audit entries linked to this person or their authored content, including report history.
            $this->db->prepare(
                "DELETE FROM moderation_logs WHERE actor_user_id=? OR (target_type='user' AND target_id=?) OR (target_type='joke' AND target_id IN (SELECT id FROM jokes WHERE user_id=?)) OR (target_type='comment' AND target_id IN (SELECT id FROM comments WHERE user_id=? OR joke_id IN (SELECT id FROM jokes WHERE user_id=?)))"
            )->execute([$id, $id, $id, $id, $id]);
            $this->db->prepare(
                "DELETE FROM moderation_logs WHERE target_type='report' AND target_id IN (SELECT id FROM reports WHERE reporter_id=?)"
            )->execute([$id]);

            // Delete user-authored reports, comments, and jokes
            $this->db->prepare('DELETE FROM reports WHERE reporter_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM comments WHERE user_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM jokes WHERE user_id=?')->execute([$id]);

            // Clear nullable references and non-null audit actors before user deletion.
            $this->db->prepare('UPDATE reports SET assigned_to=NULL WHERE assigned_to=?')->execute([$id]);
            $this->db->prepare('UPDATE reports SET resolved_by=NULL WHERE resolved_by=?')->execute([$id]);
            $this->db->prepare('UPDATE jokes SET moderated_by=NULL WHERE moderated_by=?')->execute([$id]);
            $this->db->prepare('DELETE FROM moderation_logs WHERE actor_user_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM users WHERE id=?')->execute([$id]);

            // Commit all deletions before touching the filesystem
            $this->db->commit();

            // Avatars stored as BLOB disappear with the user. Only delete safe local avatar paths.
            if (!empty($u['avatar_path'])) {
                $root = realpath(dirname(__DIR__) . '/assets/uploads/avatars');
                $candidate = realpath(dirname(__DIR__) . '/' . ltrim($u['avatar_path'], '/'));

                if ($root && $candidate && str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) && is_file($candidate)) {
                    @unlink($candidate);
                }
            }
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a category only if no jokes reference it.
     *
     * @param int $id Category ID.
     * @return bool True if deleted, false if jokes exist or category not found.
     */
    public function deleteCategory(int $id): bool
    {
        // Prevent deleting categories that still contain jokes
        $s = $this->db->prepare('SELECT COUNT(*) FROM jokes WHERE category_id=?');
        $s->execute([$id]);

        if ((int) $s->fetchColumn() > 0) {
            return false;
        }

        // Delete the empty category record
        $s = $this->db->prepare('DELETE FROM categories WHERE id=?');
        $s->execute([$id]);

        return $s->rowCount() === 1;
    }

    /**
     * Fetch the 100 most recent jokes with author details and engagement counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jokes(): array
    {
        // Query latest 100 jokes joined with author info and like/comment totals
        return $this->db->query(
            '
            SELECT j.id,j.title,j.status,j.created_at,u.username,u.location,
                   (SELECT COUNT(*) FROM likes WHERE joke_id=j.id) AS likes_count,
                   (SELECT COUNT(*) FROM comments WHERE joke_id=j.id AND status<>"deleted") AS comments_count
            FROM jokes j 
            JOIN users u ON u.id=j.user_id 
            ORDER BY j.id DESC 
            LIMIT 100
        '
        )->fetchAll();
    }

    /**
     * Permanently delete a joke along with its polymorphic reports and audit logs.
     *
     * @param int $id Joke ID.
     */
    public function deleteJoke(int $id): void
    {
        // Cascade-delete joke and polymorphic references inside a transaction
        $this->db->beginTransaction();

        try {
            // Remove reports targeting comments on this joke or the joke itself
            $this->db->prepare(
                "DELETE FROM reports WHERE target_type='comment' AND target_id IN (SELECT id FROM comments WHERE joke_id=?)"
            )->execute([$id]);
            $this->db->prepare("DELETE FROM reports WHERE target_type='joke' AND target_id=?")->execute([$id]);
            // Remove moderation audit logs referencing the joke or its comments
            $this->db->prepare(
                "DELETE FROM moderation_logs WHERE (target_type='joke' AND target_id=?) OR (target_type='comment' AND target_id IN (SELECT id FROM comments WHERE joke_id=?))"
            )->execute([$id, $id]);
            // Delete the joke row
            $this->db->prepare('DELETE FROM jokes WHERE id=?')->execute([$id]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * List all categories ordered by ID with non-deleted joke counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        // Query all categories with correlated active joke counts
        return $this->db->query(
            'SELECT c.*, (SELECT COUNT(*) FROM jokes j WHERE j.category_id=c.id AND j.status<>"deleted") joke_count FROM categories c ORDER BY c.id'
        )->fetchAll();
    }

    /**
     * Create a new category or update an existing one.
     *
     * @param int|null $id          Category ID (null to insert).
     * @param string   $name        Display name.
     * @param string   $slug        URL-friendly unique slug.
     * @param string   $description Category description.
     * @param bool     $active      Whether the category is selectable by users.
     */
    public function saveCategory(?int $id, string $name, string $slug, string $description, bool $active): void
    {
        // Update existing category or insert a new one
        if ($id) {
            $s = $this->db->prepare('UPDATE categories SET name=?,slug=?,description=?,is_active=? WHERE id=?');
            $s->execute([$name, $slug, $description, (int) $active, $id]);
        } else {
            $s = $this->db->prepare('INSERT INTO categories(name,slug,description,is_active) VALUES(?,?,?,?)');
            $s->execute([$name, $slug, $description, (int) $active]);
        }
    }

    /**
     * Retrieve editable public and gameplay system settings.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        // Fetch editable site branding and Joke Battle round count settings
        return $this->db->query(
            "SELECT setting_key,setting_value,description FROM system_settings WHERE setting_key IN ('site_name','site_slogan','joke_battle_rounds') ORDER BY setting_key"
        )->fetchAll();
    }

    /**
     * Insert or update a system setting key-value pair.
     *
     * @param string $key   Setting identifier.
     * @param string $value Setting value.
     * @param int    $actor Acting administrator user ID.
     */
    public function setSetting(string $key, string $value, int $actor): void
    {
        // Upsert setting value and mark public visibility for branding keys
        $s = $this->db->prepare(
            'INSERT INTO system_settings(setting_key,setting_value,is_public,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)'
        );
        $s->execute([$key, $value, (int) in_array($key, ['site_name', 'site_slogan'], true), $actor]);
    }
}
