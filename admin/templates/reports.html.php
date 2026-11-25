<?php
/**
 * Moderation center template for pending jokes, user reports, and recent comments.
 * Rendered by the moderator/admin reports controller with `$jokes`, `$reports`,
 * `$comments`, and optional `$commentSort` / `$commentDate` filter state.
 *
 * @var array $jokes
 * @var array $reports
 * @var array $comments
 * @var string|null $commentSort
 * @var string|null $commentDate
 */

use App\Core\{Csrf, Url}; // Import CSRF token and URL helpers.

// Resolve active comment filter/sort values from controller variables or query string.
$commentSort = $commentSort ?? (string) ($_GET['comment_sort'] ?? 'date_desc'); // Default comment sort to newest first.
$commentDate = $commentDate ?? (string) ($_GET['comment_date'] ?? ''); // Read optional comment date filter.
$approvedJokes  = $approvedJokes ?? [];
$rejectedJokes  = $rejectedJokes ?? [];
$hiddenComments = $hiddenComments ?? [];
$activeTab = (string) ($_GET['tab'] ?? (!empty($_GET['comment_sort']) || !empty($_GET['comment_date']) ? 'comments' : 'pending'));
if (!in_array($activeTab, ['pending', 'approved', 'rejected', 'comments', 'hidden-comments', 'reports'], true)) {
  $activeTab = 'pending';
}
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">KEEP THE COMMUNITY KIND</span>
  <h1>Moderation Center 🛡</h1>
  <p>Review pending jokes, browse approved/rejected history, moderate comments, and resolve user reports.</p>
</div>

<!-- Moderation section tabs -->
<nav class="inline-actions" aria-label="Moderation tabs" style="margin-bottom:18px;flex-wrap:wrap;gap:8px;">
  <button type="button" class="btn <?= $activeTab === 'pending' ? 'primary' : 'ghost' ?>" data-mod-tab="pending">
    Pending (<?= count($jokes) ?>)
  </button>
  <button type="button" class="btn <?= $activeTab === 'approved' ? 'primary' : 'ghost' ?>" data-mod-tab="approved">
    Approved (<?= count($approvedJokes) ?>)
  </button>
  <button type="button" class="btn <?= $activeTab === 'rejected' ? 'primary' : 'ghost' ?>" data-mod-tab="rejected">
    Rejected (<?= count($rejectedJokes) ?>)
  </button>
  <button type="button" class="btn <?= $activeTab === 'comments' ? 'primary' : 'ghost' ?>" data-mod-tab="comments">
    Comments (<?= count($comments) ?>)
  </button>
  <button type="button" class="btn <?= $activeTab === 'hidden-comments' ? 'primary' : 'ghost' ?>" data-mod-tab="hidden-comments">
    Hidden Comments (<?= count($hiddenComments) ?>)
  </button>
  <button type="button" class="btn <?= $activeTab === 'reports' ? 'primary' : 'ghost' ?>" data-mod-tab="reports">
    Reports (<?= count($reports) ?>)
  </button>
</nav>

<!-- Pending jokes awaiting approval or rejection -->
<section class="panel" data-mod-panel="pending" <?= $activeTab !== 'pending' ? 'hidden' : '' ?>>
  <h2>Pending jokes (<?= count($jokes) ?>)</h2>
  <?php foreach ($jokes as $j): ?>
    <div class="moderation-item">
      <div>
        <span class="pill"><?= e($j['category_name']) ?></span>
        <h3><?= e($j['title']) ?></h3>
        <div class="joke-body"><?= sanitize_joke_html($j['content']) ?></div>
        <small>by <?= e($j['username']) ?> · <?= e($j['created_at']) ?></small>
      </div>
      <form method="post" action="<?= e(Url::to('/moderator/jokes/' . $j['id'])) ?>" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <input name="reason" maxlength="1000" placeholder="Reason (required for reject/hide)">
        <div class="inline-actions">
          <button class="btn primary" name="action" value="approve">Approve</button>
          <button class="btn ghost" name="action" value="reject">Reject</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$jokes): ?>
    <p>All caught up! ✨</p>
  <?php endif; ?>
</section>

<!-- Approved (published) jokes history -->
<section class="panel" data-mod-panel="approved" <?= $activeTab !== 'approved' ? 'hidden' : '' ?>>
  <h2>Approved jokes (<?= count($approvedJokes) ?>)</h2>
  <div class="recent-comments-scroll">
    <?php foreach ($approvedJokes as $aj): ?>
      <div class="moderation-scroll-item">
        <div class="comment-main-content">
          <span class="pill"><?= e($aj['category_name']) ?></span>
          <span class="pill subtle">Approved</span>
          <h3><?= e($aj['title']) ?></h3>
          <div class="joke-body"><?= sanitize_joke_html($aj['content']) ?></div>
          <small>
            by <?= e($aj['username']) ?> · <?= e($aj['created_at']) ?>
            <?php if (!empty($aj['moderator_name'])): ?>
              · Approved by <strong><?= e($aj['moderator_name']) ?></strong><?= !empty($aj['moderated_at']) ? ' (' . e($aj['moderated_at']) . ')' : '' ?>
            <?php endif; ?>
          </small>
        </div>
        <a class="btn ghost" style="flex-shrink:0;" href="<?= e(Url::to('/jokes/' . (int) $aj['id'] . '?from=moderation&tab=approved')) ?>">View</a>
      </div>
    <?php endforeach; ?>
    <?php if (!$approvedJokes): ?>
      <p class="empty-notice">No approved jokes yet.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Rejected jokes history with rejection reasons -->
<section class="panel" data-mod-panel="rejected" <?= $activeTab !== 'rejected' ? 'hidden' : '' ?>>
  <h2>Rejected jokes (<?= count($rejectedJokes) ?>)</h2>
  <?php foreach ($rejectedJokes as $rj): ?>
    <div class="moderation-item">
      <div>
        <span class="pill"><?= e($rj['category_name']) ?></span>
        <span class="pill subtle">Rejected</span>
        <h3><?= e($rj['title']) ?></h3>
        <div class="joke-body"><?= sanitize_joke_html($rj['content']) ?></div>
        <?php if (!empty($rj['moderation_note'])): ?>
          <p style="margin-top:8px;"><strong>Reason:</strong> <?= e($rj['moderation_note']) ?></p>
        <?php endif; ?>
        <small>
          by <?= e($rj['username']) ?> · <?= e($rj['created_at']) ?>
          <?php if (!empty($rj['moderator_name'])): ?>
            · Rejected by <strong><?= e($rj['moderator_name']) ?></strong><?= !empty($rj['moderated_at']) ? ' (' . e($rj['moderated_at']) . ')' : '' ?>
          <?php endif; ?>
        </small>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rejectedJokes): ?>
    <p>No rejected jokes yet.</p>
  <?php endif; ?>
</section>

<!-- Hidden comments history with hide reasons -->
<section class="panel" data-mod-panel="hidden-comments" <?= $activeTab !== 'hidden-comments' ? 'hidden' : '' ?>>
  <h2>Hidden comments (<?= count($hiddenComments) ?>)</h2>
  <?php foreach ($hiddenComments as $hc): ?>
    <div class="moderation-item comment-mod-item">
      <div class="comment-main-content">
        <div class="comment-author-line">
          <strong class="comment-user-name"><?= e($hc['username']) ?></strong>
          <span class="comment-joke-ref">on “<?= e($hc['joke_title']) ?>” (#<?= (int) $hc['joke_id'] ?>)</span>
          <span class="comment-date-badge">
            <span>📅 <?= e(substr((string) $hc['created_at'], 0, 10)) ?></span>
            <span>🕒 <?= e(substr((string) $hc['created_at'], 11, 8)) ?></span>
          </span>
        </div>
        <p class="comment-text-body"><?= nl2br(e($hc['content'])) ?></p>
        <p style="margin-top:6px;">
          <strong>Hide reason:</strong> <?= e($hc['hide_reason'] ?? 'No reason recorded') ?>
          <?php if (!empty($hc['moderator_name'])): ?>
            <small> · Hidden by <strong><?= e($hc['moderator_name']) ?></strong><?= !empty($hc['hidden_at']) ? ' (' . e($hc['hidden_at']) . ')' : '' ?></small>
          <?php endif; ?>
        </p>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$hiddenComments): ?>
    <p>No hidden comments yet.</p>
  <?php endif; ?>
</section>

<!-- User-submitted abuse reports -->
<section class="panel" data-mod-panel="reports" <?= $activeTab !== 'reports' ? 'hidden' : '' ?>>
  <h2>Reports (<?= count($reports) ?>)</h2>
  <?php foreach ($reports as $r): ?>
    <div class="moderation-item">
      <div>
        <span class="pill"><?= e($r['status']) ?></span>
        <h3><?= e($r['target_type']) ?> #<?= (int) $r['target_id'] ?> — <?= e($r['reason']) ?></h3>
        <p><?= e($r['details'] ?? '') ?></p>
        <small>Reported by <?= e($r['reporter_name']) ?></small>
      </div>
      <?php if (in_array($r['status'], ['pending', 'reviewing'], true)): ?>
        <form method="post" action="<?= e(Url::to('/moderator/reports/' . $r['id'])) ?>" class="stacked-form">
          <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
          <input name="reason" required maxlength="1000" placeholder="Resolution note">
          <div class="inline-actions">
            <button class="btn primary" name="action" value="hide">Hide content</button>
            <button class="btn ghost" name="action" value="dismiss">Dismiss</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$reports): ?>
    <p>No reports yet.</p>
  <?php endif; ?>
</section>

<!-- Recent comments with date/time filtering and hide controls -->
<section class="panel recent-comments-panel" data-mod-panel="comments" <?= $activeTab !== 'comments' ? 'hidden' : '' ?>>
  <div class="recent-comments-head">
    <div>
      <h2>Recent comments (<?= count($comments) ?>)</h2>
      <p class="section-sub">Latest member comments across jokes with moderation controls.</p>
    </div>

    <div class="comments-sort-bar">
      <!-- Date filter and date-order dropdown -->
      <div class="comments-sort-group">
        <span class="sort-title">DATE</span>
        <details class="th-dropdown" id="dd-comment-date">
          <summary
            class="th-icon-btn <?= !empty($commentDate) || in_array($commentSort, ['date_desc', 'date_asc', 'newest', 'oldest'], true) ? 'is-active' : '' ?>"
            title="Date Options"
            aria-label="Date Options"
          >
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <?php if (!empty($commentDate) || in_array($commentSort, ['date_desc', 'date_asc', 'newest', 'oldest'], true)): ?>
              <span class="th-active-dot"></span>
            <?php endif; ?>
          </summary>
          <div class="th-menu th-menu-date">
            <div class="th-menu-title">Filter by Day</div>
            <div class="th-date-input-wrap">
              <input type="date" class="th-date-native" value="<?= e($commentDate) ?>" onchange="submitCommentFilter('comment_date', this.value)" title="Choose date">
            </div>
            <div class="th-menu-divider"></div>
            <div class="th-menu-title">Sort by Date</div>
            <button type="button" class="th-menu-item <?= $commentSort === 'date_desc' || $commentSort === 'newest' ? 'is-selected' : '' ?>" onclick="submitCommentFilter('comment_sort', 'date_desc')">
              <span>Newest</span>
              <?= $commentSort === 'date_desc' || $commentSort === 'newest' ? '<span class="th-check">✓</span>' : '' ?>
            </button>
            <button type="button" class="th-menu-item <?= $commentSort === 'date_asc' || $commentSort === 'oldest' ? 'is-selected' : '' ?>" onclick="submitCommentFilter('comment_sort', 'date_asc')">
              <span>Oldest</span>
              <?= $commentSort === 'date_asc' || $commentSort === 'oldest' ? '<span class="th-check">✓</span>' : '' ?>
            </button>
            <?php if (!empty($commentDate) || $commentSort === 'date_asc' || $commentSort === 'oldest'): ?>
              <button type="button" class="th-menu-item th-menu-clear" onclick="submitCommentFilter('comment_date', ''); submitCommentFilter('comment_sort', 'date_desc');">Reset Date</button>
            <?php endif; ?>
          </div>
        </details>
      </div>

      <!-- Time-of-day sort dropdown -->
      <div class="comments-sort-group">
        <span class="sort-title">TIME</span>
        <details class="th-dropdown" id="dd-comment-time">
          <summary
            class="th-icon-btn <?= in_array($commentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true) ? 'is-active' : '' ?>"
            title="Sort Time"
            aria-label="Sort Time"
          >
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
            <?php if (in_array($commentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true)): ?>
              <span class="th-active-dot"></span>
            <?php endif; ?>
          </summary>
          <div class="th-menu th-menu-time">
            <div class="th-menu-title">Sort by Time</div>
            <button type="button" class="th-menu-item <?= $commentSort === 'time_asc' || $commentSort === 'earliest' ? 'is-selected' : '' ?>" onclick="submitCommentFilter('comment_sort', 'time_asc')">
              <span>Earliest</span>
              <?= $commentSort === 'time_asc' || $commentSort === 'earliest' ? '<span class="th-check">✓</span>' : '' ?>
            </button>
            <button type="button" class="th-menu-item <?= $commentSort === 'time_desc' || $commentSort === 'latest' ? 'is-selected' : '' ?>" onclick="submitCommentFilter('comment_sort', 'time_desc')">
              <span>Latest</span>
              <?= $commentSort === 'time_desc' || $commentSort === 'latest' ? '<span class="th-check">✓</span>' : '' ?>
            </button>
            <?php if (in_array($commentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true)): ?>
              <button type="button" class="th-menu-item th-menu-clear" onclick="submitCommentFilter('comment_sort', 'date_desc');">Reset Time</button>
            <?php endif; ?>
          </div>
        </details>
      </div>
    </div>
  </div>

  <div class="recent-comments-scroll" id="recent-comments-scroll">
    <?php foreach ($comments as $c): ?>
      <div class="moderation-item comment-mod-item">
        <div class="comment-main-content">
          <div class="comment-author-line">
            <strong class="comment-user-name"><?= e($c['username']) ?></strong>
            <span class="comment-joke-ref">on joke #<?= (int) $c['joke_id'] ?></span>
            <span class="comment-date-badge">
              <span>📅 <?= e(substr($c['created_at'], 0, 10)) ?></span>
              <span>🕒 <?= e(substr($c['created_at'], 11, 8)) ?></span>
            </span>
          </div>
          <p class="comment-text-body"><?= nl2br(e($c['content'])) ?></p>
        </div>
        <form method="post" action="<?= e(Url::to('/moderator/comments/' . $c['id'])) ?>" class="stacked-form">
          <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
          <input name="reason" required maxlength="1000" placeholder="Reason for hiding">
          <button class="btn ghost">Hide comment</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$comments): ?>
      <p class="empty-notice">No comments match the selected date or filter.</p>
    <?php endif; ?>
  </div>
</section>

<script>
// Switch between Moderation Center tabs and persist ?tab= in the URL.
document.addEventListener('DOMContentLoaded', function () {
  const buttons = document.querySelectorAll('[data-mod-tab]');
  const panels = document.querySelectorAll('[data-mod-panel]');
  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const target = btn.getAttribute('data-mod-tab');
      buttons.forEach(function (b) {
        const active = b.getAttribute('data-mod-tab') === target;
        b.classList.toggle('primary', active);
        b.classList.toggle('ghost', !active);
      });
      panels.forEach(function (p) {
        p.hidden = p.getAttribute('data-mod-panel') !== target;
      });
      const url = new URL(window.location.href);
      url.searchParams.set('tab', target);
      window.history.replaceState({}, '', url.toString());
    });
  });
});

// Update a comment filter/sort query parameter and reload on the comments tab.
function submitCommentFilter(key, val) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', 'comments');
  if (val) {
    url.searchParams.set(key, val);
  } else {
    url.searchParams.delete(key);
  }
  window.location.href = url.toString();
}
</script>
