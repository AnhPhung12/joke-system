<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Profile — Data model for user profiles, activity stats, avatars, notifications, and account deletion.
 *
 * Purpose:
 *   Provides read/write operations for user profile metadata, binary/preset avatars,
 *   favorites, comment history, playground game history, notifications, and safe account deletion.
 * Connections:
 *   - Used by App\Controllers\ProfileController and avatar/notification endpoints.
 *   - Queries users, jokes, comments, game_sessions, game_messages, favorites, likes, and notifications.
 * OOP:
 *   - Final model class encapsulating a PDO connection.
 * Data Structures & Algorithms:
 *   - Binary Avatars: Stores image BLOBs directly in MySQL using PDO::PARAM_LOB.
 *   - Account Deletion: Transactional anonymization with FOR UPDATE lock; guards against
 *     deleting the last active administrator account.
 */
final class Profile
{
    // Shared PDO database connection for profile queries and updates.
    private PDO $db;

    public function __construct()
    {
        // Acquire the singleton PDO connection.
        $this->db = Database::connect();
    }

    /**
     * Fetch public and editable profile fields for a user.
     */
    public function detail(int $id): array
    {
        // Query user profile columns and whether a binary avatar BLOB exists.
        $s = $this->db->prepare(
            'SELECT id, username, email, bio, location, avatar_path, (avatar_data IS NOT NULL) AS has_avatar, created_at FROM users WHERE id = ?'
        );
        $s->execute([$id]);

        // Return the profile row or an empty array if not found.
        return $s->fetch() ?: [];
    }

    /**
     * Aggregate counts of non-deleted jokes, comments, game wins, and saved/liked jokes.
     */
    public function stats(int $id): array
    {
        // Define per-metric count queries for jokes, comments, and game wins.
        $q = [
            'jokes' => "SELECT COUNT(*) FROM jokes WHERE user_id=? AND status <> 'deleted'",
            'comments' => "SELECT COUNT(*) FROM comments WHERE user_id=? AND status <> 'deleted'",
            'wins' => "SELECT COUNT(*) FROM game_sessions WHERE user_id=? AND result='win'",
        ];

        // Execute each metric query and cast the scalar count to int.
        $out = [];
        foreach ($q as $k => $sql) {
            $s = $this->db->prepare($sql);
            $s->execute([$id]);
            $out[$k] = (int) $s->fetchColumn();
        }

        // Count distinct jokes that the user has either favorited or liked.
        $s = $this->db->prepare(
            'SELECT COUNT(DISTINCT joke_id) FROM (SELECT joke_id FROM favorites WHERE user_id=? UNION SELECT joke_id FROM likes WHERE user_id=?) AS selected_jokes'
        );
        $s->execute([$id, $id]);
        $out['favorites'] = (int) $s->fetchColumn();

        // Return the aggregated profile statistics map.
        return $out;
    }

    /**
     * List up to 50 published jokes that the user has liked or favorited.
     */
    public function favorites(int $id): array
    {
        // Fetch up to 50 published jokes liked or favorited by the user, newest first.
        $s = $this->db->prepare(
            "SELECT j.id, j.title, j.content, j.created_at, c.name AS category, COALESCE(l.created_at, f.created_at) AS saved_at "
            . "FROM jokes j "
            . "JOIN categories c ON c.id = j.category_id "
            . "LEFT JOIN likes l ON l.joke_id = j.id AND l.user_id = ? "
            . "LEFT JOIN favorites f ON f.joke_id = j.id AND f.user_id = ? "
            . "WHERE j.status = 'published' AND (l.user_id IS NOT NULL OR f.user_id IS NOT NULL) "
            . "ORDER BY saved_at DESC "
            . "LIMIT 50"
        );
        $s->execute([$id, $id]);

        // Return the saved joke rows.
        return $s->fetchAll();
    }

    /**
     * List up to 50 non-deleted comments authored by the user.
     */
    public function comments(int $id): array
    {
        // Query up to 50 non-deleted comments joined with their parent joke titles.
        $s = $this->db->prepare(
            "SELECT co.id, co.content, co.created_at, j.id AS joke_id, j.title AS joke_title "
            . "FROM comments co "
            . "JOIN jokes j ON j.id = co.joke_id "
            . "WHERE co.user_id = ? AND co.status <> 'deleted' "
            . "ORDER BY co.created_at DESC "
            . "LIMIT 50"
        );
        $s->execute([$id]);

        // Return the user's comment history.
        return $s->fetchAll();
    }

    /**
     * Prune empty game sessions without user turns, then return up to 50 played sessions.
     */
    public function history(int $id): array
    {
        // Delete abandoned game sessions where the user never submitted a turn.
        $cleanup = $this->db->prepare(
            "DELETE gs FROM game_sessions gs WHERE gs.user_id=? AND NOT EXISTS (SELECT 1 FROM game_messages gm WHERE gm.session_id=gs.id AND gm.sender='user')"
        );
        $cleanup->execute([$id]);

        // Fetch up to 50 played game sessions ordered by most recent start time.
        $s = $this->db->prepare(
            "SELECT id, game_type, status, player_score, ai_score, result, started_at "
            . "FROM game_sessions "
            . "WHERE user_id = ? AND EXISTS (SELECT 1 FROM game_messages gm WHERE gm.session_id = game_sessions.id AND gm.sender = 'user') "
            . "ORDER BY started_at DESC "
            . "LIMIT 50"
        );
        $s->execute([$id]);

        // Return the filtered game session history.
        return $s->fetchAll();
    }

    /**
     * Fetch the 50 most recent notifications for the user.
     */
    public function notifications(int $id): array
    {
        // Query the 50 newest notification records for this user.
        $s = $this->db->prepare(
            'SELECT id, title, message, target_url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $s->execute([$id]);

        // Return all fetched notification rows.
        return $s->fetchAll();
    }

    /**
     * Count unread notifications for the user.
     */
    public function unreadCount(int $id): int
    {
        // Count notifications where is_read is 0 for this user.
        $s = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $s->execute([$id]);

        // Return the unread count as an integer.
        return (int) $s->fetchColumn();
    }

    /**
     * Update username, bio, and location (defaulting empty location to 'United Kingdom').
     */
    public function save(int $id, string $username, string $bio, string $location = ''): void
    {
        // Persist updated profile metadata, falling back to 'United Kingdom' if location is empty.
        $s = $this->db->prepare('UPDATE users SET username = ?, bio = ?, location = ? WHERE id = ?');
        $s->execute([$username, $bio, $location ?: 'United Kingdom', $id]);
    }

    /**
     * Store an uploaded avatar image binary in the database and clear any preset path.
     */
    public function saveAvatarBinary(int $id, string $binary, string $mime): void
    {
        // Enforce allowlisted image MIME types before storing the BLOB.
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Invalid image format');
        }

        // Bind the binary payload as a LOB and clear any previous preset avatar path.
        $s = $this->db->prepare('UPDATE users SET avatar_data = ?, avatar_mime = ?, avatar_path = NULL WHERE id = ?');
        $s->bindValue(1, $binary, \PDO::PARAM_LOB);
        $s->bindValue(2, $mime);
        $s->bindValue(3, $id, \PDO::PARAM_INT);
        $s->execute();
    }

    /**
     * Select one of the 12 built-in preset avatars and clear any custom binary avatar.
     */
    public function savePresetAvatar(int $id, int $preset): void
    {
        // Validate that the preset index is within the 1..12 range.
        if ($preset < 1 || $preset > 12) {
            throw new \InvalidArgumentException('Invalid preset avatar');
        }

        // Save the preset identifier and clear any stored custom avatar binary.
        $s = $this->db->prepare('UPDATE users SET avatar_path = ?, avatar_data = NULL, avatar_mime = NULL WHERE id = ?');
        $s->execute(['preset:' . $preset, $id]);
    }

    /**
     * Retrieve the raw avatar BLOB and MIME type for a non-deleted user, if present.
     */
    public function avatar(int $id): ?array
    {
        // Fetch avatar binary data and MIME type for active or suspended users (exclude deleted).
        $s = $this->db->prepare("SELECT avatar_data, avatar_mime FROM users WHERE id = ? AND status <> 'deleted' LIMIT 1");
        $s->execute([$id]);
        $row = $s->fetch();

        // Return the avatar row only if binary data is present.
        return $row && $row['avatar_data'] !== null ? $row : null;
    }

    /**
     * Verify the current password and update it to a new hashed password.
     */
    public function changePassword(int $id, string $old, string $new): bool
    {
        // Fetch the user's current password hash.
        $s = $this->db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $s->execute([$id]);
        $hash = $s->fetchColumn();

        // Abort if the hash is missing or the current password does not match.
        if (!is_string($hash) || !password_verify($old, $hash)) {
            return false;
        }

        // Persist the new hashed password.
        $s = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $s->execute([password_hash($new, PASSWORD_DEFAULT), $id]);

        // Indicate that the password was updated.
        return true;
    }

    /**
     * Anonymize and suspend an account after password verification.
     * Refuses to delete the last remaining active administrator.
     */
    public function deleteAccount(int $id, string $password): bool
    {
        // Begin an atomic transaction for account anonymization and cleanup.
        $this->db->beginTransaction();

        try {
            // Lock the user row for update and verify status and password.
            $s = $this->db->prepare('SELECT password_hash, status FROM users WHERE id = ? FOR UPDATE');
            $s->execute([$id]);
            $user = $s->fetch();

            // Abort if the account is missing, inactive, or the password fails verification.
            if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
                $this->db->rollBack();
                return false;
            }

            // Do not remove the final active administrator.
            $q = $this->db->prepare(
                "SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.role_name = 'admin'"
            );
            $q->execute([$id]);

            // If this user holds the admin role, ensure at least one other active admin remains.
            if ((int) $q->fetchColumn() > 0) {
                $n = (int) $this->db->query(
                    "SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE r.role_name = 'admin' AND u.status = 'active'"
                )->fetchColumn();

                // Roll back and refuse deletion if this is the last active administrator.
                if ($n <= 1) {
                    $this->db->rollBack();
                    return false;
                }
            }

            // Preserve relational integrity while removing account access and identifiable profile fields.
            $alias = 'deleted_' . $id . '_' . bin2hex(random_bytes(5));
            $s = $this->db->prepare(
                "UPDATE users SET username = ?, email = ?, password_hash = ?, bio = NULL, avatar_path = NULL, avatar_data = NULL, avatar_mime = NULL, status = 'suspended' WHERE id = ?"
            );
            $s->execute([
                $alias,
                $alias . '@deleted.invalid',
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                $id,
            ]);

            // Remove role assignments, favorites, likes, and notifications for the deleted user.
            $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM favorites WHERE user_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM likes WHERE user_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);

            // Commit the anonymization and cleanup changes.
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back if an error occurred while the transaction is active.
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Mark a single notification as read for the owning user.
     */
    public function markRead(int $id, int $userId): void
    {
        // Update is_read = 1 scoped to both notification ID and owning user ID.
        $s = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $s->execute([$id, $userId]);
    }

    /**
     * Mark all notifications as read for the given user.
     */
    public function markAllRead(int $userId): void
    {
        // Mark all notifications belonging to the user as read.
        $s = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $s->execute([$userId]);
    }
}
