<?php
/** @var array<int, array<string, mixed>> $authors */
?>
<section class="page-heading">
    <span class="eyebrow">THE PEOPLE BEHIND THE LAUGHS</span>
    <h1>Joke Authors</h1>
    <p>Meet the JOKÉ community and explore the jokes they have shared.</p>
</section>

<?php if ($authors): ?>
    <section class="joke-grid" aria-label="Joke authors">
        <?php foreach ($authors as $author): ?>
            <article class="joke-card">
                <span class="pill">Community author</span>
                <h2><?= e($author['username']) ?></h2>
                <?php if (!empty($author['location'])): ?>
                    <p><?= e($author['location']) ?></p>
                <?php endif; ?>
                <p><?= (int) $author['published_jokes'] ?> published jokes</p>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <div class="empty-state">
        <h2>No authors to show yet</h2>
        <p>Authors will appear here after accounts have been created.</p>
    </div>
<?php endif; ?>
