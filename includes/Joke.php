<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Joke — Data-Access Model for the `jokes` Table (Search, Pagination, CRUD, Likes/Favorites).
 *
 * Purpose: Provides parameterized search/filtering, paginated feeds, single-joke
 *          lookups, random discovery, user submissions, and transactional
 *          like/favorite toggles.
 * Connections: Called by `App\Controllers\JokeController`, `CommunityController`,
 *              and `DatabaseFunctions`; uses `App\Core\Database::connect()`.
 * Algorithms & Data Structures:
 *   - Parameterized dynamic WHERE builder (`searchWhere()`) prevents SQL injection.
 *   - Fixed-page-size pagination (`PAGE_SIZE = 12`) with `LIMIT`/`OFFSET` and a
 *     `match()`-based sort whitelist.
 *   - Transactional `FOR UPDATE` toggle for `likes` and `favorites`.
 */
final class Joke
{
    // Number of jokes displayed per paginated feed page
    private const PAGE_SIZE = 12;

    /**
     * Fetch all active joke categories sorted alphabetically.
     *
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        // Query active categories ordered by display name
        return Database::connect()
            ->query('SELECT id,name FROM categories WHERE is_active=1 ORDER BY name')
            ->fetchAll();
    }

    /**
     * Build a parameterized WHERE clause and bound parameter array for published jokes.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function searchWhere(string $q, int $category): array
    {
        // Restrict public feed to published jokes only
        $where = ' WHERE j.status="published"';
        $params = [];

        // Add parameterized title/content LIKE filter when search query is non-empty
        if ($q !== '') {
            $where .= ' AND (j.title LIKE :q1 OR j.content LIKE :q2)';
            $params['q1'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }

        // Add parameterized category filter when a valid category ID is selected
        if ($category > 0) {
            $where .= ' AND j.category_id=:category';
            $params['category'] = $category;
        }

        // Return SQL predicate fragment and bound parameters
        return [$where, $params];
    }

    /**
     * Count total published jokes matching the current search and category filter.
     */
    public function countSearch(string $q = '', int $category = 0): int
    {
        // Reuse shared WHERE builder for consistent pagination totals
        [$where, $params] = $this->searchWhere($q, $category);
        // Prepare and execute count query
        $stmt = Database::connect()->prepare('SELECT COUNT(*) FROM jokes j' . $where);
        $stmt->execute($params);

        // Cast scalar count to integer
        return (int) $stmt->fetchColumn();
    }

    /**
     * Search and paginate published jokes with author, category, like, and comment counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $q = '', int $category = 0, string $sort = 'newest', int $page = 1): array
    {
        // Whitelist-mapped sort expression
        $sortSql = match ($sort) {
            'popular' => 'likes_count DESC, j.created_at DESC',
            'oldest' => 'j.created_at ASC',
            default => 'j.created_at DESC',
        };

        // Build parameterized WHERE clause and bind values
        [$where, $params] = $this->searchWhere($q, $category);

        // Assemble paginated feed query with correlated like and visible comment counts
        $sql = 'SELECT j.*, u.username, c.name AS category_name, '
            . '(SELECT COUNT(*) FROM likes l WHERE l.joke_id=j.id) AS likes_count, '
            . '(SELECT COUNT(*) FROM comments co WHERE co.joke_id=j.id AND co.status="visible") AS comments_count '
            . 'FROM jokes j JOIN users u ON u.id=j.user_id JOIN categories c ON c.id=j.category_id'
            . $where;
        // Append whitelisted ORDER BY and clamped LIMIT/OFFSET pagination math
        $sql .= ' ORDER BY ' . $sortSql . ' LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . max(0, ($page - 1) * self::PAGE_SIZE);

        // Prepare, execute, and return paginated joke rows
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Find a single joke by ID along with author, category, and like count.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        // Fetch joke details joined with author, category, and total likes
        $s = Database::connect()->prepare(
            'SELECT j.*,u.username,c.name AS category_name,(SELECT COUNT(*) FROM likes WHERE joke_id=j.id) AS likes_count FROM jokes j JOIN users u ON u.id=j.user_id JOIN categories c ON c.id=j.category_id WHERE j.id=?'
        );
        // Execute with joke ID
        $s->execute([$id]);

        // Return associative row or null if missing
        return $s->fetch() ?: null;
    }

    /**
     * Pick a random published joke ID for the "Surprise Me" feature.
     */
    public function randomPublishedId(): ?int
    {
        // Select one random published joke ID
        $id = Database::connect()
            ->query("SELECT id FROM jokes WHERE status='published' ORDER BY RAND() LIMIT 1")
            ->fetchColumn();

        // Return integer ID or null if no published jokes exist
        return $id === false ? null : (int) $id;
    }

    /**
     * Fetch all non-deleted jokes authored by a specific user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mine(int $userId): array
    {
        // Query user's authored jokes excluding soft-deleted entries
        $s = Database::connect()->prepare(
            'SELECT j.*,c.name category_name FROM jokes j JOIN categories c ON c.id=j.category_id WHERE j.user_id=? AND j.status<>"deleted" ORDER BY j.created_at DESC'
        );
        // Execute with author user ID
        $s->execute([$userId]);

        return $s->fetchAll();
    }

    /**
     * Insert a new joke in `pending` moderation status with server-side HTML sanitization.
     *
     * @param array<string, mixed> $data
     */
    public function create(int $userId, array $data): void
    {
        // Prepare insert with initial 'pending' moderation status
        $s = Database::connect()->prepare(
            'INSERT INTO jokes(user_id,category_id,title,content,country_code,language_code,status) VALUES(?,?,?,?,?, ?,"pending")'
        );
        // Sanitize rich-text content server-side before persisting
        $s->execute([
            $userId,
            $data['category_id'],
            $data['title'],
            sanitize_joke_html($data['content']),
            $data['country_code'],
            'en',
        ]);
    }

    /**
     * Update an existing joke and reset its moderation status to `pending` for re-review.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        // Update joke fields and clear prior moderation metadata for re-approval
        $s = Database::connect()->prepare(
            'UPDATE jokes SET category_id=?,title=?,content=?,country_code=?,language_code=?,status="pending",moderation_note=NULL,moderated_by=NULL,moderated_at=NULL,published_at=NULL WHERE id=?'
        );
        // Sanitize updated rich-text content and bind parameters
        $s->execute([
            $data['category_id'],
            $data['title'],
            sanitize_joke_html($data['content']),
            $data['country_code'],
            'en',
            $id,
        ]);
    }

    /**
     * Soft-delete a joke by setting its status to `deleted`.
     */
    public function delete(int $id): void
    {
        // Mark joke status as 'deleted' rather than removing the row
        $s = Database::connect()->prepare('UPDATE jokes SET status="deleted" WHERE id=?');
        $s->execute([$id]);
    }

    /**
     * Atomically toggle a user's like or favorite on a joke inside a transaction.
     */
    public function toggle(int $userId, int $jokeId, string $table): void
    {
        // Whitelist target interaction table name to prevent SQL injection
        if (!in_array($table, ['likes', 'favorites'], true)) {
            throw new \InvalidArgumentException('Invalid interaction');
        }

        // Acquire PDO connection and start ACID transaction
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock any existing interaction row for this user and joke
            $s = $pdo->prepare("SELECT id FROM {$table} WHERE user_id=? AND joke_id=? FOR UPDATE");
            $s->execute([$userId, $jokeId]);
            $id = $s->fetchColumn();

            // Delete existing interaction or insert a new one
            if ($id) {
                $s = $pdo->prepare("DELETE FROM {$table} WHERE id=?");
                $s->execute([$id]);
            } else {
                $s = $pdo->prepare("INSERT INTO {$table}(user_id,joke_id) VALUES(?,?)");
                $s->execute([$userId, $jokeId]);
            }

            // Commit toggle transaction
            $pdo->commit();
        } catch (\Throwable $e) {
            // Roll back on any database error
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Check whether a user has liked or favorited a specific joke.
     */
    public function isSelected(int $userId, int $jokeId, string $table): bool
    {
        // Validate interaction table whitelist
        if (!in_array($table, ['likes', 'favorites'], true)) {
            return false;
        }

        // Query existence of user-joke interaction row
        $s = Database::connect()->prepare("SELECT 1 FROM {$table} WHERE user_id=? AND joke_id=?");
        $s->execute([$userId, $jokeId]);

        // Cast result to boolean
        return (bool) $s->fetchColumn();
    }
}
