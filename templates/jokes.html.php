<?php
/**
 * Joke Library Listing Template
 *
 * Renders the searchable, filterable, and paginated public joke catalog.
 * Connected to the JokeController index action; supports keyword search,
 * category filtering, sort order, and windowed pagination links.
 * Uses `tr()` for UI strings and `e()` for XSS-safe output.
 *
 * @var string $q
 * @var array $categories
 * @var int $category
 * @var string $sort
 * @var array $jokes
 * @var int $page
 * @var int $pageCount
 * @var int $total
 */

use App\Core\{Auth, Url}; // Import auth and URL helpers
?>
<!-- Public joke library heading -->
<div class="page-heading">
    <span class="eyebrow"><?= e(tr('EXPLORE THE FUN')) ?></span>
    <h1><?= e(tr('Joke Library')) ?> <span class="chibi chibi-sparkle chibi-inline" aria-hidden="true"></span></h1>
    <p><?= e(tr('Funny stories from the UK, Vietnam, Gen Z and beyond.')) ?></p>
</div>

<!-- Search, category, and sort filter bar -->
<form class="filters" method="get" action="<?= e(Url::to('/jokes')) ?>">
    <!-- Escaped search query input -->
    <input name="q" placeholder="<?= e(tr('Search jokes...')) ?>" value="<?= e($q) ?>" maxlength="100">

    <!-- Category filter dropdown -->
    <select name="category">
        <option value="0"><?= e(tr('All categories')) ?></option>
        <?php foreach ($categories as $cat): // Render each category option and mark active selection ?>
            <option value="<?= (int)$cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= e($cat['name']) ?>
            </option>
        <?php endforeach; // End category options loop ?>
    </select>

    <!-- Sort order selector -->
    <select name="sort">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= e(tr('Newest')) ?></option>
        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>><?= e(tr('Oldest')) ?></option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>><?= e(tr('Most liked')) ?></option>
    </select>

    <button class="btn primary"><?= e(tr('Search')) ?></button>
</form>

<!-- Section heading with authenticated Create Joke CTA -->
<div class="section-actions">
    <h2><?= e(tr('Discover jokes')) ?></h2>
    <?php if (Auth::user()): // Show create button only to signed-in users ?>
        <a class="btn primary" href="<?= e(Url::to('/jokes/create')) ?>">+ <?= e(tr('Create Joke')) ?></a>
    <?php endif; // End auth CTA check ?>
</div>

<!-- Joke cards grid -->
<div class="joke-grid">
    <?php foreach ($jokes as $joke): // Render each joke card in the current page ?>
        <article class="joke-card">
            <span class="pill"><?= e($joke['category_name']) ?></span>
            <h3>
                <a href="<?= e(Url::to('/jokes/' . $joke['id'])) ?>"><?= e($joke['title']) ?></a>
            </h3>
            <!-- Strip HTML tags and truncate joke preview to 200 characters -->
            <p><?= e(mb_strimwidth(joke_plain_text($joke['content']), 0, 200, '…', 'UTF-8')) ?></p>
            <div class="joke-meta">
                <span>by <?= e($joke['username']) ?><?php if (!empty($joke['created_at'])): ?> · <?= e(date('M j, Y', strtotime((string)$joke['created_at']))) ?><?php endif; ?></span>
                <span>♥ <?= (int)$joke['likes_count'] ?> · ◌ <?= (int)$joke['comments_count'] ?></span>
            </div>
            <a class="text-link" href="<?= e(Url::to('/jokes/' . $joke['id'])) ?>"><?= e(tr('Read joke')) ?> →</a>
        </article>
    <?php endforeach; // End joke cards loop ?>
</div>

<?php if (!$jokes): // Show empty state when no jokes match active filters ?>
    <div class="empty-state">
        <div class="blob">☺</div>
        <h3><?= e(tr('Not found joke')) ?></h3>
        <p><?= e(tr('Try another search or create your first joke!')) ?></p>
    </div>
<?php endif; // End empty state check ?>

<?php
// Build pagination URL helper preserving active search/filter query parameters
$pageUrl = static fn(int $number): string => Url::to('/jokes') . '?' . http_build_query([
    'q'        => $q,
    'category' => $category,
    'sort'     => $sort,
    'page'     => $number,
]);
$first = max(1, $page - 3); // Lower bound of the 7-page sliding window
$last = min($pageCount, $page + 3); // Upper bound of the 7-page sliding window
?>

<!-- Windowed pagination controls -->
<?php if ($total > 0): // Render pagination bar only when results exist ?>
    <nav class="pagination" aria-label="Joke pages">
        <?php if ($page > 1): // Previous page link when not on first page ?>
            <a class="btn ghost" href="<?= e($pageUrl($page - 1)) ?>">← <?= e(tr('Previous')) ?></a>
        <?php endif; ?>

        <?php if ($first > 1): // Leading page 1 link and ellipsis when window is shifted ?>
            <a class="btn ghost" href="<?= e($pageUrl(1)) ?>">1</a>
            <?php if ($first > 2): // Ellipsis gap between page 1 and window start ?>
                <span aria-hidden="true">…</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($number = $first; $number <= $last; $number++): // Render page buttons within sliding window ?>
            <a
                class="btn <?= $number === $page ? 'primary' : 'ghost' ?>"
                <?= $number === $page ? 'aria-current="page"' : '' ?>
                href="<?= e($pageUrl($number)) ?>"
            >
                <?= $number ?>
            </a>
        <?php endfor; // End page window loop ?>

        <?php if ($last < $pageCount): // Trailing ellipsis and last page link when window ends early ?>
            <?php if ($last < $pageCount - 1): // Ellipsis gap between window end and final page ?>
                <span aria-hidden="true">…</span>
            <?php endif; ?>
            <a class="btn ghost" href="<?= e($pageUrl($pageCount)) ?>"><?= $pageCount ?></a>
        <?php endif; ?>

        <?php if ($page < $pageCount): // Next page link when not on final page ?>
            <a class="btn ghost" href="<?= e($pageUrl($page + 1)) ?>"><?= e(tr('Next')) ?> →</a>
        <?php endif; ?>
    </nav>
<?php endif; // End pagination check ?>
