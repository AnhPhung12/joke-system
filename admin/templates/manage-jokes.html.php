<?php
/**
 * Admin joke inventory management template.
 * Rendered by the `/admin/jokes` controller with `$jokes` (rows containing
 * `id`, `title`, `username`, `location`, `likes_count`, `comments_count`,
 * `status`, and `created_at`). Provides view and permanent-delete actions.
 *
 * @var array $jokes
 */

use App\Core\{Csrf, Url}; // Import CSRF token and URL helpers.

$perPage = 5;
$totalJokes = count($jokes);
$pageCount = max(1, (int) ceil($totalJokes / $perPage));
$page = max(1, min($pageCount, (int) ($_GET['page'] ?? 1)));
$pagedJokes = array_slice($jokes, ($page - 1) * $perPage, $perPage);
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">CONTENT</span>
  <h1>Manage Jokes</h1>
  <p>Review published, pending and hidden jokes. Track likes, comments, and authors across the community.</p>
</div>

<!-- Joke inventory table panel -->
<section class="panel staff-table-panel">
  <div class="staff-panel-heading">
    <div>
      <h2>Joke Inventory</h2>
      <p>Total <?= count($jokes) ?> jokes published by community members.</p>
    </div>
    <a class="btn primary" href="<?= e(Url::to('/jokes/create')) ?>">+ Add New Joke</a>
  </div>

  <div class="table-wrap">
    <table class="data-table staff-data-table">
      <thead>
        <tr>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Joke</span>
            </div>
          </th>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Author</span>
            </div>
          </th>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Likes</span>
            </div>
          </th>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Comments</span>
            </div>
          </th>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Status</span>
            </div>
          </th>
          <th>
            <div class="th-header-cell">
              <span class="th-title">Date</span>
            </div>
          </th>
          <th class="th-actions">
            <div class="th-header-cell th-actions-cell">
              <span class="th-title">Actions</span>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($jokes)): ?>
          <tr>
            <td colspan="7" class="staff-empty-row" style="text-align:center;padding:28px;color:var(--muted)">
              No jokes found.
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($pagedJokes as $j): // Render one table row per joke record on current page.
          $id = (int) $j['id']; // Cast joke ID to integer.
          $createdAt = !empty($j['created_at']) ? strtotime((string) $j['created_at']) : null; // Parse creation timestamp if present.
          $displayDate = $createdAt ? date('Y-m-d', $createdAt) : '—'; // Format creation date or show placeholder.
        ?>
          <tr>
            <td>
              <a class="staff-joke-link" href="<?= e(Url::to('/jokes/' . $id)) ?>">
                <strong><?= e($j['title']) ?></strong>
              </a>
              <small class="staff-subline">Joke #<?= $id ?></small>
            </td>
            <td>
              <strong><?= e($j['username']) ?></strong>
              <?php if (!empty($j['location'])): ?>
                <small class="staff-subline">📍 <?= e($j['location']) ?></small>
              <?php endif; ?>
            </td>
            <td>
              <span class="staff-stat-badge stat-likes" title="<?= (int) $j['likes_count'] ?> likes">
                ❤️ <?= (int) $j['likes_count'] ?>
              </span>
            </td>
            <td>
              <span class="staff-stat-badge stat-comments" title="<?= (int) $j['comments_count'] ?> comments">
                💬 <?= (int) $j['comments_count'] ?>
              </span>
            </td>
            <td>
              <span class="staff-status <?= $j['status'] === 'published' ? 'is-active' : 'is-muted' ?>">
                <?= e(ucfirst((string) $j['status'])) ?>
              </span>
            </td>
            <td>
              <span class="staff-date"><?= e($displayDate) ?></span>
            </td>
            <td>
              <div class="staff-row-actions">
                <a class="btn ghost staff-action" href="<?= e(Url::to('/jokes/' . $id)) ?>">View</a>
                <form
                  method="post"
                  action="<?= e(Url::to('/admin/jokes/' . $id . '/delete')) ?>"
                  onsubmit="return confirm('Permanently delete this joke and its comments?')"
                  style="display:inline;margin:0;"
                >
                  <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                  <button class="btn ghost staff-action staff-danger">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pageCount > 1): ?>
    <nav class="pagination" aria-label="Manage jokes pages" style="margin-top:16px;">
      <?php if ($page > 1): ?>
        <a class="btn ghost" href="<?= e(Url::to('/admin/jokes')) ?>?page=<?= $page - 1 ?>">← Previous</a>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $pageCount; $p++): ?>
        <a class="btn <?= $p === $page ? 'primary' : 'ghost' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> href="<?= e(Url::to('/admin/jokes')) ?>?page=<?= $p ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $pageCount): ?>
        <a class="btn ghost" href="<?= e(Url::to('/admin/jokes')) ?>?page=<?= $page + 1 ?>">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>

<p style="margin-top:16px;">
  <a href="<?= e(Url::to('/admin/reports')) ?>">Review pending jokes and reports →</a>
</p>
