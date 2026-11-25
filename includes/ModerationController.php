<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Moderation;

/**
 * ModerationController — MVC controller for the Moderation Center workflow.
 *
 * Purpose:
 *   Exposes the moderation queue view and handles moderator actions on jokes, comments, and reports.
 * Connections:
 *   - Delegates data mutations to App\Models\Moderation.
 *   - Enforces 'moderation.review' permission via Auth::requirePermission() and CSRF via Csrf::enforce().
 *   - Renders templates/moderation-queue.html.php via View::render('moderation/queue', ...).
 * OOP:
 *   - Final stateless controller class.
 * Data Structures & Algorithms:
 *   - Validates action enums and reason length (<= 1000 chars) before delegating to transactional model methods.
 */
final class ModerationController
{
    /**
     * Display the moderation center with pending jokes, open reports, and recent comments.
     */
    public function queue(): void
    {
        // Require moderation review permission before loading queue data.
        Auth::requirePermission('moderation.review');

        // Instantiate the Moderation model and render the queue template.
        $m = new Moderation();
        View::render('moderation/queue', [
            'title' => 'Moderation Center',
            'jokes' => $m->queue(),
            'reports' => $m->reports(),
            'comments' => $m->comments(),
        ]);
    }

    /**
     * Process a moderator decision (approve, reject, or hide) on a joke.
     */
    public function joke(int $id): void
    {
        // Enforce moderation permission and CSRF token validity.
        Auth::requirePermission('moderation.review');
        Csrf::enforce();

        // Read the submitted moderation action and trimmed reason note.
        $action = (string) ($_POST['action'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));

        // Validate action enum, require a reason for reject/hide, and cap length at 1000 chars.
        if (
            !in_array($action, ['approve', 'reject', 'hide'], true)
            || (($action === 'reject' || $action === 'hide') && $reason === '')
            || mb_strlen($reason) > 1000
        ) {
            http_response_code(422);
            exit('Invalid moderation action');
        }

        // Apply the joke moderation action and flash feedback before redirecting.
        $ok = (new Moderation())->reviewJoke($id, (int) Auth::user()['id'], $action, $reason);
        Session::flash($ok ? 'Joke moderation status updated.' : 'You cannot moderate your own joke or the joke is invalid.');
        $targetTab = 'pending';
        if ($ok && $action === 'approve') {
            $targetTab = 'approved';
        } elseif ($ok && $action === 'reject') {
            $targetTab = 'rejected';
        }
        Url::redirect('/admin/reports?tab=' . $targetTab);
    }

    /**
     * Hide a comment with a mandatory moderation reason.
     */
    public function comment(int $id): void
    {
        // Enforce moderation permission and CSRF token validity.
        Auth::requirePermission('moderation.review');
        Csrf::enforce();

        // Require a non-empty moderation reason within the 1000-character limit.
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 1000) {
            http_response_code(422);
            exit('A reason is required.');
        }

        // Hide the comment, flash the outcome, and redirect to the hidden comments tab.
        $ok = (new Moderation())->reviewComment($id, (int) Auth::user()['id'], $reason);
        Session::flash($ok ? 'Comment hidden.' : 'Unable to moderate this comment.');
        Url::redirect('/admin/reports?tab=' . ($ok ? 'hidden-comments' : 'comments'));
    }

    /**
     * Resolve or dismiss a user report with a mandatory resolution note.
     */
    public function report(int $id): void
    {
        // Enforce moderation permission and CSRF token validity.
        Auth::requirePermission('moderation.review');
        Csrf::enforce();

        // Read the resolution action and trimmed note from POST input.
        $action = (string) ($_POST['action'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));

        // Validate the action enum and require a non-empty reason <= 1000 characters.
        if (!in_array($action, ['hide', 'dismiss'], true) || $reason === '' || mb_strlen($reason) > 1000) {
            http_response_code(422);
            exit('Invalid report action');
        }

        // Resolve or dismiss the report, flash feedback, and redirect back to the reports tab.
        $ok = (new Moderation())->resolveReport($id, (int) Auth::user()['id'], $action, $reason);
        Session::flash($ok ? 'Report resolved.' : 'Unable to resolve this report.');
        Url::redirect('/admin/reports?tab=reports');
    }
}
