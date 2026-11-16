<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Comment — Data-Access Model for Threaded Joke Comments.
 *
 * Purpose: Manages CRUD and visibility operations for comments on published jokes,
 *          supporting one-level parent/reply threading.
 * Connections: Called by `App\Controllers\CommunityController` and
 *              `App\Controllers\ModerationController`; uses `App\Core\Database`.
 * Data Structures & Algorithms:
 *   - Uses `ORDER BY COALESCE(co.parent_id, co.id), co.parent_id IS NOT NULL, co.created_at, co.id`
 *     to group replies immediately beneath their parent comment in a single SQL pass.
 */
final class Comment
{
    /**
     * Fetch all visible comments for a joke, ordered as parent-first threads.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forJoke(int $jokeId): array
    {
        // Query visible comments joined with author usernames, grouping replies under their parent
        $s = Database::connect()->prepare(
            'SELECT co.*,u.username FROM comments co JOIN users u ON u.id=co.user_id WHERE co.joke_id=? AND co.status="visible" ORDER BY COALESCE(co.parent_id,co.id),co.parent_id IS NOT NULL,co.created_at,co.id'
        );
        // Bind the target joke ID
        $s->execute([$jokeId]);

        // Return all matching comment rows
        return $s->fetchAll();
    }

    /**
     * Find a single comment by its primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        // Fetch the comment record by ID
        $s = Database::connect()->prepare('SELECT * FROM comments WHERE id=?');
        // Execute with the comment ID
        $s->execute([$id]);

        // Return the associative row or null if not found
        return $s->fetch() ?: null;
    }

    /**
     * Insert a new comment or reply on a joke.
     */
    public function add(int $jokeId, int $userId, string $content, ?int $parent): void
    {
        // Prepare insert for a top-level comment or reply
        $s = Database::connect()->prepare(
            'INSERT INTO comments(joke_id,user_id,parent_id,content) VALUES(?,?,?,?)'
        );
        // Persist the comment row
        $s->execute([$jokeId, $userId, $parent, $content]);
    }

    /**
     * Hide a comment during moderation.
     */
    public function hide(int $id): void
    {
        // Mark the comment as hidden from public view
        $s = Database::connect()->prepare('UPDATE comments SET status="hidden" WHERE id=?');
        // Execute the status update
        $s->execute([$id]);
    }

    /**
     * Soft-delete a comment by marking its status as `deleted`.
     */
    public function delete(int $id): void
    {
        // Soft-delete the comment while preserving thread integrity
        $s = Database::connect()->prepare('UPDATE comments SET status="deleted" WHERE id=?');
        // Execute the soft-delete update
        $s->execute([$id]);
    }

    /**
     * Update the body text of a visible comment.
     */
    public function update(int $id, string $content): void
    {
        // Update comment text only if the comment is currently visible
        $s = Database::connect()->prepare('UPDATE comments SET content=? WHERE id=? AND status="visible"');
        // Bind new content and comment ID
        $s->execute([$content, $id]);
    }
}
