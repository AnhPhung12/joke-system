<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * User — Core account model for authentication lookups, registration, and login timestamps.
 *
 * Purpose:
 *   Fetches user records by email or ID (with attached RBAC role names), creates new user accounts
 *   with the default 'user' role inside a transaction, and updates last_login_at.
 * Connections:
 *   - Used by AuthCore, AuthController, PasswordReset, and ProfileController.
 *   - Queries users, roles, and user_roles tables via Database::connect().
 * OOP:
 *   - Final model class holding a PDO instance.
 * Data Structures & Algorithms:
 *   - Transactional Registration: Inserts into users and user_roles atomically, rolling back
 *     if the default 'user' role is missing from the roles table.
 */
final class User
{
    // Shared PDO database connection for user account queries.
    private PDO $db;

    public function __construct()
    {
        // Acquire the singleton PDO connection.
        $this->db = Database::connect();
    }

    /**
     * Find a user record (including password_hash) by email address.
     */
    public function findByEmail(string $email): ?array
    {
        // Query a single user row matching the given email address.
        $s = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $s->execute([$email]);

        // Return the associative user record or null if not found.
        return $s->fetch() ?: null;
    }

    /**
     * Find a user's public profile fields by ID and attach their assigned role names.
     */
    public function findById(int $id): ?array
    {
        // Fetch non-sensitive user fields and avatar presence flag by ID.
        $s = $this->db->prepare(
            'SELECT id, username, email, location, avatar_path, (avatar_data IS NOT NULL) AS has_avatar, status, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $s->execute([$id]);
        $user = $s->fetch();

        // Return null early if no user exists with the given ID.
        if (!$user) {
            return null;
        }

        // Load all RBAC role names assigned to this user.
        $r = $this->db->prepare(
            'SELECT r.role_name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?'
        );
        $r->execute([$id]);
        $user['roles'] = $r->fetchAll(PDO::FETCH_COLUMN);

        // Return the enriched user array with attached roles.
        return $user;
    }

    /**
     * Check whether a username or email is already registered.
     */
    public function exists(string $username, string $email): bool
    {
        // Query whether any user row already uses the given username or email.
        $s = $this->db->prepare('SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1');
        $s->execute([$username, $email]);
        return (bool) $s->fetchColumn();
    }

    /**
     * Register a new user account and assign the default 'user' role atomically.
     */
    public function create(string $username, string $email, string $password, string $location = 'Vietnam', bool $alreadyHashed = false): int
    {
        // Start a transaction so user creation and role assignment succeed or fail together.
        $this->db->beginTransaction();

        try {
            // Normalize location (max 120 chars) and hash the password unless already hashed.
            $loc = trim($location) !== '' ? mb_substr(trim($location), 0, 120) : 'Vietnam';
            $hash = $alreadyHashed ? $password : password_hash($password, PASSWORD_DEFAULT);

            // Insert the new user record with a strongly hashed password and location.
            $stmt = $this->db->prepare('INSERT INTO users (username, email, password_hash, location) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $email, $hash, $loc]);
            $id = (int) $this->db->lastInsertId();

            // Assign the default 'user' role from the roles lookup table.
            $role = $this->db->prepare(
                "INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE role_name = 'user'"
            );
            $role->execute([$id]);

            // Fail if the 'user' role seed row is missing from the database.
            if ($role->rowCount() !== 1) {
                throw new \RuntimeException('Missing user role. Import schema first.');
            }

            // Commit the new user and role assignment and return the user ID.
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            // Roll back on any database or schema error.
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Record the current timestamp as the user's last login time.
     */
    public function touchLogin(int $id): void
    {
        // Update the user's last_login_at column to the current database timestamp.
        $s = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $s->execute([$id]);
    }
}
