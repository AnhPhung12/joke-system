<?php
/**
 * Joke Detail Template
 *
 * Displays a single joke with sanitized rich HTML content, like/favorite toggles,
 * threaded comments (top-level comments + single-level replies), comment deletion,
 * and content reporting forms for both comments and the joke itself.
 * All POST forms include CSRF tokens; plain text is escaped via `e()`.
 *
 * @var array $joke
 * @var bool $liked
 * @var bool $saved
 * @var array $comments
 */

use App\Core\{Auth, Csrf, Url}; // Import auth, CSRF, and URL helpers

$me = Auth::user(); // Fetch currently signed-in user or null for guests
$isStaff = $me && (Auth::hasRole('admin') || Auth::hasRole('moderator'));
$fromModeration = $isStaff && (($_GET['from'] ?? '') === 'moderation');
$backTab = (string) ($_GET['tab'] ?? 'approved');
if (!in_array($backTab, ['pending', 'approved', 'rejected', 'comments', 'hidden-comments', 'reports'], true)) {
    $backTab = 'approved';
}
?>
<!-- Back link -->
<?php if ($fromModeration): ?>
  <a class="text-link" href="<?= e(Url::to('/admin/reports?tab=' . urlencode($backTab))) ?>">← Back to Moderation Center</a>
<?php else: ?>
  <a class="text-link" href="<?= e(Url::to('/jokes')) ?>">← Back to Library</a>
<?php endif; ?>

<!-- Joke content card -->
<article class="detail-card">
    <span class="pill"><?= e($joke['category_name']) ?></span>
    <span class="pill subtle"><?= e($joke['status']) ?></span>
    <h1><?= e($joke['title']) ?></h1>
    <!-- Render sanitized rich-text HTML body -->
    <div class="joke-body"><?= sanitize_joke_html($joke['content']) ?></div>
    <div class="joke-meta">
        <span>✦ <?= e($joke['username']) ?> · <?= e($joke['country_code'] ?? '') ?><?php if (!empty($joke['created_at'])): ?> · 🕒 <?= e(date('Y-m-d H:i', strtotime((string)$joke['created_at']))) ?><?php endif; ?></span>
        <span>♥ <?= (int)$joke['likes_count'] ?></span>
    </div>

    <?php if ($me && !$isStaff && $joke['status'] === 'published'): // Allow like/favorite actions for signed-in clients on published jokes ?>
        <div class="inline-actions">
            <!-- Like toggle form with CSRF protection -->
            <form method="post" action="<?= e(Url::to('/jokes/' . $joke['id'] . '/like')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <button class="btn <?= $liked ? 'primary' : 'ghost' ?>">
                    <?= $liked ? '♥ Liked' : '♡ Like' ?>
                </button>
            </form>

            <!-- Favorite toggle form with CSRF protection -->
            <form method="post" action="<?= e(Url::to('/jokes/' . $joke['id'] . '/favorite')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <button class="btn <?= $saved ? 'primary' : 'ghost' ?>">
                    <?= $saved ? '★ Saved' : '☆ Favorite' ?>
                </button>
            </form>
        </div>
    <?php endif; // End like/favorite actions check ?>
</article>

<!-- Comments section -->
<section class="panel">
    <h2>Comments (<?= count($comments) ?>)</h2>

    <?php if ($me && !$isStaff && $joke['status'] === 'published'): // Show top-level comment form for signed-in clients ?>
        <form class="stacked-form" method="post" action="<?= e(Url::to('/jokes/' . $joke['id'] . '/comments')) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <textarea name="content" maxlength="2000" required placeholder="Write a friendly comment..."></textarea>
            <button class="btn primary">Post comment</button>
        </form>
    <?php elseif (!$me): // Prompt guests to sign in before commenting ?>
        <p><a class="text-link" href="<?= e(Url::to('/login')) ?>">Sign in to comment</a></p>
    <?php endif; // End comment composer check ?>

    <?php foreach ($comments as $comment): // Render each comment and single-level reply ?>
        <div class="comment <?= $comment['parent_id'] ? 'reply' : '' ?>">
            <div class="joke-meta">
                <strong><?= e($comment['username']) ?></strong>
                <small><?= e($comment['created_at']) ?></small>
            </div>
            <!-- Escape comment text and preserve line breaks -->
            <p><?= nl2br(e($comment['content'])) ?></p>

            <?php if ($me && !$isStaff): // Show reply, delete, or report actions for signed-in clients ?>
                <div class="inline-actions">
                    <?php if ($joke['status'] === 'published' && !$comment['parent_id']): // Allow replies only on top-level comments ?>
                        <details>
                            <summary class="text-link">Reply</summary>
                            <!-- Reply submission form with parent_id and CSRF token -->
                            <form method="post" action="<?= e(Url::to('/jokes/' . $joke['id'] . '/comments')) ?>" class="stacked-form">
                                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
                                <textarea name="content" required maxlength="2000" placeholder="Reply..."></textarea>
                                <button class="btn primary">Send reply</button>
                            </form>
                        </details>
                    <?php endif; // End reply form check ?>

                    <?php if ((int)$me['id'] === (int)$comment['user_id']): // Allow authors to delete their own comments ?>
                        <form method="post" action="<?= e(Url::to('/comments/' . $comment['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this comment?')">
                            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                            <button class="btn ghost">Delete</button>
                        </form>
                    <?php else: // Allow non-authors to report a comment ?>
                        <details>
                            <summary class="text-link">Report</summary>
                            <!-- Comment report form with CSRF token -->
                            <form method="post" action="<?= e(Url::to('/reports')) ?>" class="stacked-form">
                                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                <input type="hidden" name="target_type" value="comment">
                                <input type="hidden" name="target_id" value="<?= (int)$comment['id'] ?>">
                                <select name="reason">
                                    <option value="spam">Spam</option>
                                    <option value="harassment">Harassment</option>
                                    <option value="other">Other</option>
                                </select>
                                <textarea name="details" maxlength="1000" placeholder="Reason for reporting"></textarea>
                                <button class="btn ghost">Submit report</button>
                            </form>
                        </details>
                    <?php endif; // End comment author vs. reporter branch ?>
                </div>
            <?php endif; // End signed-in comment actions check ?>
        </div>
    <?php endforeach; // End comments loop ?>
</section>

<!-- Joke reporting panel (shown to signed-in non-authors on published jokes) -->
<?php if ($me && !$isStaff && (int)$me['id'] !== (int)$joke['user_id'] && $joke['status'] === 'published'): // Restrict joke reporting to non-author signed-in clients ?>
    <section class="panel">
        <h3>Report this joke</h3>
        <!-- Joke report form with CSRF protection -->
        <form method="post" action="<?= e(Url::to('/reports')) ?>" class="stacked-form">
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="target_type" value="joke">
            <input type="hidden" name="target_id" value="<?= (int)$joke['id'] ?>">
            <select name="reason">
                <option value="spam">Spam</option>
                <option value="harassment">Harassment</option>
                <option value="hate">Hate</option>
                <option value="copyright">Copyright</option>
                <option value="other">Other</option>
            </select>
            <textarea name="details" maxlength="1000" placeholder="Describe the issue..."></textarea>
            <button class="btn ghost">Send report</button>
        </form>
    </section>
<?php endif; // End joke report panel check ?>
