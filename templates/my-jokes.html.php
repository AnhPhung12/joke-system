<?php
/**
 * My Jokes Template
 *
 * Lists the signed-in user's own jokes (all statuses) with moderator notes
 * and view/edit/delete actions. Delete forms are CSRF-protected and prompt
 * for confirmation; all text is escaped via `e()`.
 *
 * @var array $jokes
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- My Jokes header with create button -->
<div class="section-actions">
    <div class="page-heading">
        <span class="eyebrow">MY CREATIONS</span>
        <h1>My Jokes <span class="chibi chibi-blush chibi-inline" aria-hidden="true"></span></h1>
    </div>
    <a class="btn primary" href="<?= e(Url::to('/jokes/create')) ?>">+ New Joke</a>
</div>

<!-- User's jokes grid -->
<div class="joke-grid">
    <?php foreach ($jokes as $joke): // Render each joke created by the current user ?>
        <article class="joke-card">
            <span class="pill"><?= e($joke['status']) ?></span>
            <h3><?= e($joke['title']) ?></h3>
            <!-- Strip HTML and truncate joke content preview to 180 chars -->
            <p><?= e(mb_strimwidth(joke_plain_text($joke['content']), 0, 180, '…', 'UTF-8')) ?></p>

            <?php if ($joke['moderation_note']): // Display moderator feedback if present ?>
                <p class="note">Moderator: <?= e($joke['moderation_note']) ?></p>
            <?php endif; // End moderation note check ?>

            <div class="inline-actions">
                <a class="btn ghost" href="<?= e(Url::to('/jokes/' . $joke['id'])) ?>">View</a>
                <a class="btn ghost" href="<?= e(Url::to('/jokes/' . $joke['id'] . '/edit')) ?>">Edit</a>
                <!-- Delete joke form with confirmation dialog and CSRF token -->
                <form
                    method="post"
                    action="<?= e(Url::to('/jokes/' . $joke['id'] . '/delete')) ?>"
                    onsubmit="return confirm('Delete this joke?')"
                >
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <button class="btn ghost">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; // End user jokes loop ?>
</div>

<?php if (!$jokes): // Show empty state when user has not created any jokes ?>
    <div class="empty-state">
        <span class="chibi chibi-blush chibi-auth" aria-hidden="true"></span>
        <h3>No jokes yet</h3>
        <p>Write something funny to get started!</p>
    </div>
<?php endif; // End empty state check ?>
