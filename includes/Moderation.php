<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Moderation — Data model for content moderation queues, user reports, and audit logs.
 *
 * Purpose:
 *   Manages pending joke reviews, visible comment moderation, user-submitted reports,
 *   notifications to affected users, and immutable moderation audit logging.
 * Connections:
 *   - Used by App\Controllers\ModerationController and admin/moderator entrypoints.
 *   - Interacts with jokes, comments, reports, notifications, and moderation_logs tables via Database::connect().
 * OOP:
 *   - Final model class using PDO transactions.
 * Data Structures & Algorithms:
 *   - Concurrency: Uses SELECT ... FOR UPDATE row-level locks inside transactions to prevent
 *     concurrent moderators from overwriting each other's decisions.
 *   - Self-Moderation Guard: Prevents moderators from reviewing their own jokes, comments, or reports.
 */
final class Moderation
{
    /**
     * Fetch up to 100 pending jokes ordered oldest-first for FIFO review.
     */
    public function queue(): array
    {
        // Query up to 100 pending jokes joined with author and category names in FIFO order.
        return Database::connect()
            ->query(
                'SELECT j.id, j.title, j.content, j.created_at, u.username, c.name category_name '
                . 'FROM jokes j '
                . 'JOIN users u ON u.id = j.user_id '
                . 'JOIN categories c ON c.id = j.category_id '
                . 'WHERE j.status = "pending" '
                . 'ORDER BY j.created_at ASC '
                . 'LIMIT 100'
            )
            ->fetchAll();
    }

    /**
     * Fetch up to 100 jokes matching a moderation status (`published` or `rejected`) with reviewer info.
     */
    public function jokesByStatus(string $status): array
    {
        if (!in_array($status, ['published', 'rejected'], true)) {
            return [];
        }
        $s = Database::connect()->prepare(
            'SELECT j.id, j.title, j.content, j.created_at, j.moderation_note, j.moderated_at, j.published_at, '
            . 'u.username, c.name category_name, m.username moderator_name '
            . 'FROM jokes j '
            . 'JOIN users u ON u.id = j.user_id '
            . 'JOIN categories c ON c.id = j.category_id '
            . 'LEFT JOIN users m ON m.id = j.moderated_by '
            . 'WHERE j.status = ? '
            . 'ORDER BY COALESCE(j.moderated_at, j.created_at) DESC, j.id DESC '
            . 'LIMIT 100'
        );
        $s->execute([$status]);
        return $s->fetchAll();
    }

    /**
     * Fetch up to 100 hidden comments along with the latest moderation hide reason and actor.
     */
    public function hiddenComments(): array
    {
        return Database::connect()
            ->query(
                'SELECT co.id, co.content, co.created_at, co.joke_id, u.username, j.title joke_title, '
                . '(SELECT ml.reason FROM moderation_logs ml WHERE ml.target_type="comment" AND ml.target_id=co.id ORDER BY ml.id DESC LIMIT 1) AS hide_reason, '
                . '(SELECT mu.username FROM moderation_logs ml JOIN users mu ON mu.id=ml.actor_user_id WHERE ml.target_type="comment" AND ml.target_id=co.id ORDER BY ml.id DESC LIMIT 1) AS moderator_name, '
                . '(SELECT ml.created_at FROM moderation_logs ml WHERE ml.target_type="comment" AND ml.target_id=co.id ORDER BY ml.id DESC LIMIT 1) AS hidden_at '
                . 'FROM comments co '
                . 'JOIN users u ON u.id = co.user_id '
                . 'JOIN jokes j ON j.id = co.joke_id '
                . 'WHERE co.status = "hidden" '
                . 'ORDER BY co.id DESC '
                . 'LIMIT 100'
            )
            ->fetchAll();
    }

    /**
     * Fetch up to 100 user reports prioritized by workflow status (pending -> reviewing -> resolved -> dismissed).
     */
    public function reports(): array
    {
        // Query up to 100 reports ordered by priority status and newest creation timestamp.
        return Database::connect()
            ->query(
                'SELECT r.*, u.username reporter_name '
                . 'FROM reports r '
                . 'JOIN users u ON u.id = r.reporter_id '
                . 'ORDER BY FIELD(r.status, "pending", "reviewing", "resolved", "dismissed"), r.created_at DESC '
                . 'LIMIT 100'
            )
            ->fetchAll();
    }

    /**
     * Fetch up to 120 visible comments with optional date filtering and sort order.
     */
    public function comments(string $sort = 'date_desc', string $date = ''): array
    {
        // Restrict results to visible comments by default.
        $where = ['co.status="visible"'];
        // Initialize prepared statement parameter list.
        $params = [];

        // Append a creation date filter when a specific date is provided.
        if ($date !== '') {
            $where[] = 'DATE(co.created_at) = ?';
            $params[] = $date;
        }

        // Map the requested sort key to an allowlisted SQL ORDER BY clause.
        $order = match ($sort) {
            'date_asc', 'oldest' => 'co.created_at ASC, co.id ASC',
            'time_desc', 'latest' => 'TIME(co.created_at) DESC, co.created_at DESC',
            'time_asc', 'earliest' => 'TIME(co.created_at) ASC, co.created_at ASC',
            default => 'co.created_at DESC, co.id DESC',
        };

        // Assemble the parameterized SQL query for visible comments.
        $sql = 'SELECT co.id, co.content, co.created_at, co.joke_id, u.username '
            . 'FROM comments co '
            . 'JOIN users u ON u.id = co.user_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY ' . $order . ' '
            . 'LIMIT 120';

        // Prepare and execute the comment query with bound date parameters.
        $s = Database::connect()->prepare($sql);
        $s->execute($params);

        // Return all matching comment rows.
        return $s->fetchAll();
    }

    /**
     * Apply a moderation decision (approve, reject, hide) to a joke, log it, and notify the author.
     */
    public function reviewJoke(int $id, int $actor, string $action, string $reason): bool
    {
        // Acquire the shared PDO connection and start an atomic transaction.
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock the target joke row for update to prevent concurrent moderation races.
            $s = $pdo->prepare('SELECT status, user_id FROM jokes WHERE id = ? FOR UPDATE');
            $s->execute([$id]);
            $old = $s->fetch();

            // Abort if the joke does not exist or is in a non-reviewable state.
            if (!$old || !in_array($old['status'], ['pending', 'published', 'hidden', 'rejected'], true)) {
                $pdo->rollBack();
                return false;
            }

            // Prevent moderators from reviewing their own submissions.
            if ((int) $old['user_id'] === $actor) {
                $pdo->rollBack();
                return false;
            }

            // Map the moderation action verb to the target joke status.
            $status = match ($action) {
                'approve' => 'published',
                'reject' => 'rejected',
                'hide' => 'hidden',
                default => null,
            };

            // Abort and roll back if the action verb is unrecognized.
            if (!$status) {
                $pdo->rollBack();
                return false;
            }

            // Update the joke status, moderation metadata, and publish timestamp if approved.
            $s = $pdo->prepare(
                'UPDATE jokes '
                . 'SET status = ?, moderation_note = ?, moderated_by = ?, moderated_at = NOW(), '
                . 'published_at = CASE WHEN ? = "published" THEN NOW() ELSE published_at END '
                . 'WHERE id = ?'
            );
            $s->execute([$status, $reason, $actor, $status, $id]);

            // Record an immutable audit trail entry for this joke review.
            $this->log($pdo, $actor, 'joke.' . $action, 'joke', $id, $reason);

            // Insert a notification informing the joke author of the moderation outcome.
            $notify = $pdo->prepare(
                'INSERT INTO notifications(user_id, notification_type, title, message, target_url) VALUES(?, ?, ?, ?, ?)'
            );
            $notify->execute([
                (int) $old['user_id'],
                'joke_moderation',
                'Joke moderation update',
                'Your joke status is now ' . $status . ($reason !== '' ? ': ' . $reason : ''),
                '/jokes/' . $id,
            ]);

            // Commit the status change, audit log, and notification atomically.
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back all changes on any database or runtime error.
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Hide a visible comment, record the audit log entry, and notify the comment author.
     */
    public function reviewComment(int $id, int $actor, string $reason): bool
    {
        // Acquire the shared PDO connection and begin a transaction.
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock the comment row to inspect its current visibility and author.
            $s = $pdo->prepare('SELECT user_id, status FROM comments WHERE id = ? FOR UPDATE');
            $s->execute([$id]);
            $old = $s->fetch();

            // Abort if the comment is missing, not visible, or authored by the moderator.
            if (!$old || $old['status'] !== 'visible' || (int) $old['user_id'] === $actor) {
                $pdo->rollBack();
                return false;
            }

            // Mark the comment as hidden.
            $s = $pdo->prepare('UPDATE comments SET status = "hidden" WHERE id = ?');
            $s->execute([$id]);

            // Append an audit log entry for hiding the comment.
            $this->log($pdo, $actor, 'comment.hide', 'comment', $id, $reason);

            // Notify the comment author that their comment was hidden.
            $notify = $pdo->prepare(
                'INSERT INTO notifications(user_id, notification_type, title, message, target_url) VALUES(?, ?, ?, ?, ?)'
            );
            $notify->execute([
                (int) $old['user_id'],
                'comment_moderation',
                'Comment hidden',
                'Your comment was hidden: ' . $reason,
                '/notifications',
            ]);

            // Commit the comment update, audit log, and notification.
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back the transaction if any step fails.
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Submit a new user report against a published joke or visible comment.
     * Prevents self-reporting and duplicate open reports by the same reporter.
     */
    public function submitReport(int $actor, string $type, int $id, string $reason, string $details): bool
    {
        // Reject unsupported report target types.
        if (!in_array($type, ['joke', 'comment'], true)) {
            return false;
        }

        // Connect to the database and resolve the target table name.
        $pdo = Database::connect();
        $table = $type === 'joke' ? 'jokes' : 'comments';

        // Fetch the target item's author and status to verify report eligibility.
        $s = $pdo->prepare("SELECT user_id, status FROM {$table} WHERE id = ?");
        $s->execute([$id]);
        $target = $s->fetch();

        // Disallow reporting missing items, own content, or non-public items.
        if (!$target || (int) $target['user_id'] === $actor || !in_array($target['status'], ['published', 'visible'], true)) {
            return false;
        }

        // Prevent duplicate active reports from the same user on the same target.
        $s = $pdo->prepare(
            'SELECT 1 FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status IN ("pending", "reviewing") LIMIT 1'
        );
        $s->execute([$actor, $type, $id]);
        if ($s->fetchColumn()) {
            return false;
        }

        // Insert the new pending report record.
        $s = $pdo->prepare(
            'INSERT INTO reports(reporter_id, target_type, target_id, reason, details) VALUES(?, ?, ?, ?, ?)'
        );
        $s->execute([$actor, $type, $id, $reason, $details]);

        // Signal that the report was successfully recorded.
        return true;
    }

    /**
     * Resolve or dismiss an open report, optionally hiding the target content and notifying both parties.
     */
    public function resolveReport(int $id, int $actor, string $action, string $reason): bool
    {
        // Acquire the shared PDO connection and begin a transaction.
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Lock the report row for update to prevent concurrent resolution.
            $s = $pdo->prepare('SELECT * FROM reports WHERE id = ? FOR UPDATE');
            $s->execute([$id]);
            $report = $s->fetch();

            // Abort if the report is missing, already closed, or submitted by the acting moderator.
            if (!$report || !in_array($report['status'], ['pending', 'reviewing'], true) || (int) $report['reporter_id'] === $actor) {
                $pdo->rollBack();
                return false;
            }

            // Resolve the target content table and lock the reported row.
            $targetTable = $report['target_type'] === 'joke' ? 'jokes' : 'comments';
            $s = $pdo->prepare("SELECT user_id, status FROM {$targetTable} WHERE id = ? FOR UPDATE");
            $s->execute([$report['target_id']]);
            $target = $s->fetch();

            // Abort if the reported content is missing or authored by the acting moderator.
            if (!$target || (int) $target['user_id'] === $actor) {
                $pdo->rollBack();
                return false;
            }

            // Prepare the notification insert statement reused for author and reporter.
            $notify = $pdo->prepare(
                'INSERT INTO notifications(user_id, notification_type, title, message, target_url) VALUES(?, ?, ?, ?, ?)'
            );

            // Handle hiding the reported content and resolving the report.
            if ($action === 'hide') {
                // Hide the target joke or comment unless it is already deleted.
                $s = $pdo->prepare("UPDATE {$targetTable} SET status = 'hidden' WHERE id = ? AND status NOT IN ('deleted')");
                $s->execute([$report['target_id']]);
                $newStatus = 'resolved';

                // Notify the content author that their post was hidden.
                if ($target['user_id']) {
                    $notify->execute([
                        (int) $target['user_id'],
                        'content_moderation',
                        ucfirst($report['target_type']) . ' hidden',
                        'Your ' . $report['target_type'] . ' was hidden following a review' . ($reason !== '' ? ': ' . $reason : ''),
                        '/notifications',
                    ]);
                }

                // Notify the reporter that their report led to resolution.
                if ($report['reporter_id']) {
                    $notify->execute([
                        (int) $report['reporter_id'],
                        'report_resolution',
                        'Report resolved',
                        'Thank you for keeping JOKÉ safe. Your report on a ' . $report['target_type'] . ' was resolved.',
                        '/notifications',
                    ]);
                }
            } elseif ($action === 'dismiss') {
                // Mark the report as dismissed without altering the target content.
                $newStatus = 'dismissed';

                // Notify the reporter that the report was reviewed and dismissed.
                if ($report['reporter_id']) {
                    $notify->execute([
                        (int) $report['reporter_id'],
                        'report_resolution',
                        'Report update',
                        'Your report on a ' . $report['target_type'] . ' was reviewed and dismissed' . ($reason !== '' ? ': ' . $reason : ''),
                        '/notifications',
                    ]);
                }
            } else {
                // Abort on any unsupported report resolution action.
                $pdo->rollBack();
                return false;
            }

            // Persist the final report status, resolver ID, and resolution note.
            $s = $pdo->prepare('UPDATE reports SET status = ?, resolved_by = ?, resolution_note = ? WHERE id = ?');
            $s->execute([$newStatus, $actor, $reason, $id]);

            // Append an immutable audit log entry for the report action.
            $this->log($pdo, $actor, 'report.' . $action, 'report', $id, $reason);

            // Commit all report resolution changes atomically.
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back on any error during report resolution.
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Append an immutable audit record to moderation_logs within the active transaction.
     */
    private function log(\PDO $pdo, int $actor, string $action, string $type, int $id, string $reason): void
    {
        // Insert the moderation action record into the audit log table.
        $s = $pdo->prepare(
            'INSERT INTO moderation_logs(actor_user_id, action, target_type, target_id, reason) VALUES(?, ?, ?, ?, ?)'
        );
        $s->execute([$actor, $action, $type, $id, $reason]);
    }
}
