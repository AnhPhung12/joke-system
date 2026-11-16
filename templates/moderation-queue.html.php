<?php
/**
 * Moderation Queue Template
 *
 * Staff-only view for reviewing pending jokes, user-submitted reports, and
 * recent comments. Connected to the ModeratorController; every moderation
 * action is a CSRF-protected POST request and all output is escaped via `e()`.
 *
 * @var array $jokes
 * @var array $reports
 * @var array $comments
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- Moderation center heading -->
<div class="page-heading">
    <span class="eyebrow">KEEP THE COMMUNITY KIND</span>
    <h1>Moderation Center 🛡</h1>
    <p>Review pending jokes, reported content, and comments. Your own content cannot be moderated by you.</p>
</div>

<!-- Pending jokes awaiting approval/rejection -->
<section class="panel">
    <h2>Pending jokes (<?= count($jokes) ?>)</h2>

    <?php foreach ($jokes as $j): // Render each pending joke with approve/reject actions ?>
        <div class="moderation-item">
            <div>
                <span class="pill"><?= e($j['category_name']) ?></span>
                <h3><?= e($j['title']) ?></h3>
                <!-- Escape content and preserve line breaks -->
                <p><?= nl2br(e($j['content'])) ?></p>
                <small>by <?= e($j['username']) ?> · <?= e($j['created_at']) ?></small>
            </div>

            <!-- Joke moderation action form with CSRF token -->
            <form method="post" action="<?= e(Url::to('/moderator/jokes/' . $j['id'])) ?>" class="stacked-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <input name="reason" maxlength="1000" placeholder="Reason (required for reject/hide)">
                <div class="inline-actions">
                    <button class="btn primary" name="action" value="approve">Approve</button>
                    <button class="btn ghost" name="action" value="reject">Reject</button>
                </div>
            </form>
        </div>
    <?php endforeach; // End pending jokes loop ?>

    <?php if (!$jokes): // Show caught-up message when queue is empty ?>
        <p>All caught up! ✨</p>
    <?php endif; // End empty pending jokes check ?>
</section>

<!-- User-submitted content reports -->
<section class="panel">
    <h2>Reports (<?= count($reports) ?>)</h2>

    <?php foreach ($reports as $r): // Render each reported item and resolution controls ?>
        <div class="moderation-item">
            <div>
                <span class="pill"><?= e($r['status']) ?></span>
                <h3><?= e($r['target_type']) ?> #<?= (int)$r['target_id'] ?> — <?= e($r['reason']) ?></h3>
                <p><?= e($r['details'] ?? '') ?></p>
                <small>Reported by <?= e($r['reporter_name']) ?></small>
            </div>

            <?php if (in_array($r['status'], ['pending', 'reviewing'], true)): // Show hide/dismiss actions only for unresolved reports ?>
                <form method="post" action="<?= e(Url::to('/moderator/reports/' . $r['id'])) ?>" class="stacked-form">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <input name="reason" required maxlength="1000" placeholder="Resolution note">
                    <div class="inline-actions">
                        <button class="btn primary" name="action" value="hide">Hide content</button>
                        <button class="btn ghost" name="action" value="dismiss">Dismiss</button>
                    </div>
                </form>
            <?php endif; // End unresolved report check ?>
        </div>
    <?php endforeach; // End reports loop ?>

    <?php if (!$reports): // Show empty state when no reports exist ?>
        <p>No reports yet.</p>
    <?php endif; // End empty reports check ?>
</section>

<!-- Recent comments quick-hide list -->
<section class="panel">
    <h2>Recent comments</h2>

    <?php foreach ($comments as $c): // Render each recent comment with quick-hide form ?>
        <div class="moderation-item">
            <div>
                <h3><?= e($c['username']) ?> on joke #<?= (int)$c['joke_id'] ?></h3>
                <!-- Escape comment text and preserve line breaks -->
                <p><?= nl2br(e($c['content'])) ?></p>
            </div>

            <!-- Comment hide form with CSRF protection -->
            <form method="post" action="<?= e(Url::to('/moderator/comments/' . $c['id'])) ?>" class="stacked-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <input name="reason" required maxlength="1000" placeholder="Reason for hiding">
                <button class="btn ghost">Hide comment</button>
            </form>
        </div>
    <?php endforeach; // End recent comments loop ?>
</section>
