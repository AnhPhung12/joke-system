<?php
/**
 * Admin joke library browse template.
 * Displays searchable, filterable, and paginated joke cards.
 * Expects `$jokes`, `$categories`, `$q`, `$category`, `$sort`, and `$page`.
 *
 * @var array $jokes
 * @var array $categories
 * @var string $q
 * @var int $category
 * @var string $sort
 * @var int $page
 */

use App\Core\{Auth, Url}; // Import authentication and URL helpers.
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">EXPLORE THE FUN</span>
  <h1>Joke Library ☁</h1>
  <p>Funny stories from the UK, Vietnam, Gen Z and beyond.</p>
</div>

<!-- Search, category, and sort filter bar -->
<form class="filters" method="get" action="<?= e(Url::to('/jokes')) ?>">
  <input name="q" placeholder="Search jokes..." value="<?= e($q) ?>" maxlength="100">
  <select name="category">
    <option value="0">All categories</option>
    <?php foreach ($categories as $cat): // Render each category filter option. ?>
      <option value="<?= (int) $cat['id'] ?>" <?= $category === (int) $cat['id'] ? 'selected' : '' ?>>
        <?= e($cat['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="sort">
    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
    <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most liked</option>
  </select>
  <button class="btn primary">Search</button>
</form>

<!-- Section heading and joke creation action for logged-in users -->
<div class="section-actions">
  <h2>Discover jokes</h2>
  <?php if (Auth::user()): // Show create button only for authenticated users. ?>
    <a class="btn primary" href="<?= e(Url::to('/jokes/create')) ?>">+ Create Joke</a>
  <?php endif; ?>
</div>

<!-- Joke card grid -->
<div class="joke-grid">
  <?php foreach ($jokes as $joke): // Render each joke summary card. ?>
    <article class="joke-card">
      <span class="pill"><?= e($joke['category_name']) ?></span>
      <h3>
        <a href="<?= e(Url::to('/jokes/' . $joke['id'])) ?>"><?= e($joke['title']) ?></a>
      </h3>
      <p><?= e(mb_strimwidth(joke_plain_text($joke['content']), 0, 200, '…', 'UTF-8')) ?></p>
      <div class="joke-meta">
        <span>by <?= e($joke['username']) ?></span>
        <span>♥ <?= (int) $joke['likes_count'] ?> · ◌ <?= (int) $joke['comments_count'] ?></span>
      </div>
      <a class="text-link" href="<?= e(Url::to('/jokes/' . $joke['id'])) ?>">Read joke →</a>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!$jokes): ?>
  <div class="empty-state">
    <div class="blob">☺</div>
    <h3>Not found joke</h3>
    <p>Try another search or create your first joke!</p>
  </div>
<?php endif; ?>

<!-- Pagination controls (12 items per page) -->
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="btn ghost" href="<?= e(Url::to('/jokes') . '?' . http_build_query(['q' => $q, 'category' => $category, 'sort' => $sort, 'page' => $page - 1])) ?>">← Previous</a>
  <?php endif; ?>
  <?php if (count($jokes) === 12): ?>
    <a class="btn ghost" href="<?= e(Url::to('/jokes') . '?' . http_build_query(['q' => $q, 'category' => $category, 'sort' => $sort, 'page' => $page + 1])) ?>">Next →</a>
  <?php endif; ?>
</div>
