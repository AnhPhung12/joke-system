<?php
/**
 * User Profile Template
 *
 * Displays the user's profile hero (avatar, username, email, location, bio),
 * activity statistics, horizontal scrollable strips for favourite jokes and
 * comments, and AI Playground game history with delete controls.
 * All dynamic text is escaped via `e()` and delete forms include CSRF tokens.
 *
 * @var array $profile
 * @var array $stats
 * @var array $favorites
 * @var array $comments
 * @var array $history
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers

$preset = 0; // Default to no preset avatar selected
if (preg_match('/^preset:(1[0-2]|[1-9])$/', (string)($profile['avatar_path'] ?? ''), $m)) { // Check if avatar_path uses a valid preset (1-12)
    $preset = (int)$m[1]; // Store matched preset number
}
?>
<!-- Profile page header with settings link -->
<div class="page-heading profile-heading">
    <div>
        <span class="eyebrow">YOUR HAPPY SPACE</span>
        <h1><?= e(tr("My Profile")) ?> <span class="chibi chibi-cool chibi-inline" aria-hidden="true"></span></h1>
        <p>Your favourite jokes and game history.</p>
    </div>
    <a class="btn outline" href="<?= e(Url::to('/profile/settings')) ?>">⚙ <?= e(tr("Settings")) ?></a>
</div>

<!-- Profile identity hero -->
<div class="profile-hero panel">
    <div class="profile-photo-wrap">
        <?php if ($preset): // Render CSS preset avatar if configured ?>
            <span class="preset-avatar preset-<?= $preset ?>" aria-hidden="true"></span>
        <?php elseif (!empty($profile["has_avatar"])): // Render uploaded avatar image served via avatar.php ?>
            <img class="avatar-photo" src="<?= e(Url::base() . "/avatar.php?id=" . (int)$profile["id"]) ?>" alt="Profile photo">
        <?php else: // Fallback mascot icon when no avatar is set ?>
            <span class="mascot small" aria-hidden="true">☺</span>
        <?php endif; // End avatar display branch ?>
    </div>

    <div>
        <!-- Escaped user identity, location, join timestamp, and bio -->
        <h2><?= e($profile['username']) ?></h2>
        <p>
            <?= e($profile['email']) ?> · 📍 <?= e($profile['location'] ?? 'United Kingdom') ?> · Joined <?= e(substr($profile['created_at'], 0, 10)) ?> (<?= e(substr($profile['created_at'], 11, 8)) ?>)
        </p>
        <p><?= e($profile['bio'] ?? '') ?></p>
    </div>
</div>

<!-- Activity summary stats -->
<div class="stat-grid">
    <?php foreach (['jokes' => 'My Jokes', 'comments' => 'Comments', 'favorites' => 'Favorites', 'wins' => 'Game Wins'] as $k => $label): // Iterate over profile metric keys and labels ?>
        <div class="card">
            <span class="eyebrow"><?= e($label) ?></span>
            <h2><?= (int)$stats[$k] ?></h2>
        </div>
    <?php endforeach; // End stats loop ?>
</div>

<!-- Favourite jokes and recent comments horizontal strips (JS hooks: [data-horizontal-strip], [data-strip-prev], [data-strip-next]) -->
<div class="profile-secondary-grid">
    <section class="panel">
        <div class="profile-strip-heading">
            <h2>♡ Favorite Jokes</h2>
            <?php if (count($favorites) > 3): // Show scroll controls only when more than 3 favourites exist ?>
                <span class="profile-strip-controls">
                    <button type="button" data-strip-prev aria-label="Previous favourite jokes">‹</button>
                    <button type="button" data-strip-next aria-label="Next favourite jokes">›</button>
                </span>
            <?php endif; // End favourite strip controls check ?>
        </div>

        <?php if (!$favorites): // Show empty state when user has no favourite jokes ?>
            <p>No favourite jokes yet. <a class="text-link" href="<?= e(Url::to('/jokes')) ?>">Explore jokes</a></p>
        <?php else: // Render horizontal strip of favourite joke cards ?>
            <div class="profile-horizontal-strip" data-horizontal-strip>
                <?php foreach ($favorites as $j): // Loop through each favourite joke ?>
                    <article class="profile-strip-card">
                        <span class="pill"><?= e($j['category']) ?></span>
                        <h3><?= e($j['title']) ?></h3>
                        <!-- Convert HTML content to plain text and truncate to 110 chars -->
                        <p><?= e(mb_strimwidth(joke_plain_text($j['content']), 0, 110, '…', 'UTF-8')) ?></p>
                        <a class="text-link" href="<?= e(Url::to('/jokes/' . (int)$j['id'])) ?>">Read joke →</a>
                    </article>
                <?php endforeach; // End favourite jokes loop ?>
            </div>
        <?php endif; // End favourites branch ?>
    </section>

    <section class="panel">
        <div class="profile-strip-heading">
            <h2>✎ Your Comments</h2>
            <?php if (count($comments) > 3): // Show scroll controls only when more than 3 comments exist ?>
                <span class="profile-strip-controls">
                    <button type="button" data-strip-prev aria-label="Previous comments">‹</button>
                    <button type="button" data-strip-next aria-label="Next comments">›</button>
                </span>
            <?php endif; // End comments strip controls check ?>
        </div>

        <?php if (!$comments): // Show empty state when user has no comments ?>
            <p>You have not commented yet. <a class="text-link" href="<?= e(Url::to('/jokes')) ?>">Explore jokes</a></p>
        <?php else: // Render horizontal strip of recent comments ?>
            <div class="profile-horizontal-strip" data-horizontal-strip>
                <?php foreach ($comments as $comment): // Loop through each user comment ?>
                    <article class="profile-strip-card comment-strip-card">
                        <span class="pill subtle">On <?= e($comment['joke_title']) ?></span>
                        <!-- Truncate comment preview to 110 characters -->
                        <p><?= e(mb_strimwidth($comment['content'], 0, 110, '…', 'UTF-8')) ?></p>
                        <small><?= e($comment['created_at']) ?></small>
                        <a class="text-link" href="<?= e(Url::to('/jokes/' . (int)$comment['joke_id'])) ?>">View joke →</a>
                    </article>
                <?php endforeach; // End comments loop ?>
            </div>
        <?php endif; // End comments branch ?>
    </section>
</div>

<!-- AI Playground session history -->
<section class="panel game-history-panel">
    <div class="game-history-heading">
        <div>
            <span class="eyebrow">PLAYGROUND</span>
            <h2>AI Game History</h2>
        </div>
        <span class="chibi chibi-excited chibi-card" aria-hidden="true"></span>
    </div>

    <?php if (!$history): // Show empty state if no AI game sessions exist ?>
        <p>No game sessions yet. <a class="text-link" href="<?= e(Url::to('/playground')) ?>">Play a game</a></p>
    <?php else: // Render list of past AI game sessions ?>
        <div class="game-history-list">
            <?php foreach ($history as $g): // Loop through each game session record ?>
                <article class="game-history-card">
                    <a class="game-history-main" href="<?= e(Url::to('/playground/' . (int)$g['id'])) ?>">
                        <!-- Select emoji icon based on game_type -->
                        <span class="game-history-icon" aria-hidden="true">
                            <?= $g['game_type'] === 'word_chain' ? '🔗' : ($g['game_type'] === 'punchline_guess' ? '🎯' : '🎤') ?>
                        </span>
                        <span class="game-history-info">
                            <strong><?= e(str_replace('_', ' ', ucwords($g['game_type'], '_'))) ?></strong>
                            <small><?= e($g['started_at']) ?> · <?= e(ucfirst($g['status'])) ?></small>
                        </span>
                        <span class="game-history-score">
                            <small>Score</small>
                            <strong><?= e((string)$g['player_score']) ?></strong>
                        </span>
                        <span class="game-history-open">Open history →</span>
                    </a>

                    <!-- Delete game session form with confirmation prompt and CSRF token -->
                    <form
                        method="post"
                        action="<?= e(Url::to('/playground/' . (int)$g['id'] . '/delete')) ?>"
                        data-confirm="Delete this game history permanently?"
                    >
                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                        <button
                            class="game-history-delete"
                            type="submit"
                            aria-label="Delete this game history"
                            title="Delete history"
                        >
                            ×
                        </button>
                    </form>
                </article>
            <?php endforeach; // End game history loop ?>
        </div>
    <?php endif; // End game history branch ?>
</section>
