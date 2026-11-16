<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Session, Url, View};
use App\Models\{Comment, Joke, Moderation};

/**
 * CommunityController — MVC Controller for Comments & Content Reporting.
 *
 * Purpose: Handles authenticated user interactions on published jokes: posting
 *          top-level comments or replies, editing/deleting own comments, and
 *          submitting abuse/moderation reports.
 * Connections: Coordinates `App\Models\{Comment, Joke, Moderation}` with
 *              `App\Core\{Auth, Csrf, Session, Url}`.
 * Security: Enforces login, CSRF tokens, ownership checks for edits/deletions,
 *           and strict validation of reply parents and report reason enums.
 */
final class CommunityController
{
    /**
     * Post a new comment or single-level reply on a published joke.
     */
    public function comment(int $jokeId): void
    {
        // Require authenticated session and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Verify the target joke exists and is published
        $joke = (new Joke())->find($jokeId);
        if (!$joke || $joke['status'] !== 'published') {
            http_response_code(404);
            exit('Not found');
        }

        // Validate comment body length (1 to 2,000 characters)
        $content = trim((string) ($_POST['content'] ?? ''));
        if (mb_strlen($content) < 1 || mb_strlen($content) > 2000) {
            Session::flash('Comments must contain between 1 and 2,000 characters.');
            Url::redirect('/jokes/' . $jokeId);
        }

        // Validate optional parent comment (must belong to same joke, be top-level, and visible)
        $parent = (int) ($_POST['parent_id'] ?? 0);
        $model = new Comment();

        // Ensure replies only target visible top-level comments on the same joke
        if ($parent) {
            $original = $model->find($parent);
            if (
                !$original
                || (int) $original['joke_id'] !== $jokeId
                || $original['parent_id'] !== null
                || $original['status'] !== 'visible'
            ) {
                http_response_code(422);
                exit('Invalid reply');
            }
        }

        // Insert the comment and redirect back to the joke page
        $model->add($jokeId, (int) Auth::user()['id'], $content, $parent ?: null);
        Session::flash('Comment posted.');
        Url::redirect('/jokes/' . $jokeId);
    }

    /**
     * Edit an existing visible comment owned by the current user.
     */
    public function editComment(int $id): void
    {
        // Require authenticated session and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Load the comment and verify ownership and visibility
        $model = new Comment();
        $comment = $model->find($id);

        if (!$comment || (int) $comment['user_id'] !== (int) Auth::user()['id'] || $comment['status'] !== 'visible') {
            http_response_code(403);
            exit('Forbidden');
        }

        // Validate updated comment length
        $content = trim((string) ($_POST['content'] ?? ''));
        if (mb_strlen($content) < 1 || mb_strlen($content) > 2000) {
            Session::flash('Comments must contain between 1 and 2,000 characters.');
            Url::redirect('/jokes/' . $comment['joke_id']);
        }

        // Persist the edited comment and redirect back to the joke
        $model->update($id, $content);
        Session::flash('Comment updated.');
        Url::redirect('/jokes/' . $comment['joke_id']);
    }

    /**
     * Soft-delete a comment owned by the current user.
     */
    public function deleteComment(int $id): void
    {
        // Require authenticated session and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Load the comment and verify ownership
        $model = new Comment();
        $comment = $model->find($id);

        if (!$comment || (int) $comment['user_id'] !== (int) Auth::user()['id']) {
            http_response_code(403);
            exit('Forbidden');
        }

        // Soft-delete the comment and return to the joke page
        $model->delete($id);
        Url::redirect('/jokes/' . $comment['joke_id']);
    }

    /**
     * Submit a moderation report against a joke or comment.
     */
    public function report(): void
    {
        // Require authenticated session and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Extract report target type, ID, reason, and optional details
        $type = (string) ($_POST['target_type'] ?? '');
        $id = (int) ($_POST['target_id'] ?? 0);
        $reason = (string) ($_POST['reason'] ?? 'other');
        $details = trim((string) ($_POST['details'] ?? ''));

        // Whitelist valid report reasons and validate details length and target ID
        $valid = ['spam', 'harassment', 'hate', 'sexual_content', 'misinformation', 'copyright', 'other'];
        if (!in_array($reason, $valid, true) || mb_strlen($details) > 1000 || $id < 1) {
            http_response_code(422);
            exit('Invalid report');
        }

        // Submit the report via the Moderation model
        $ok = (new Moderation())->submitReport((int) Auth::user()['id'], $type, $id, $reason, $details);
        // Set appropriate user feedback flash message
        Session::flash(
            $ok
                ? 'Report submitted.'
                : 'Unable to submit the report: duplicate, invalid or your own content.'
        );

        // Redirect back to the associated joke page when possible
        $back = $type === 'joke'
            ? $id
            : ($type === 'comment' ? ((new Comment())->find($id)['joke_id'] ?? 0) : 0);

        Url::redirect($back ? '/jokes/' . $back : '/jokes');
    }
}
