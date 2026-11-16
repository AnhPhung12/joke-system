<?php
/**
 * AI Playground Landing Template
 *
 * Displays the three AI mini-game modes (Word Chain, Guess the Punchline,
 * Tell a Joke), a random surprise challenge card, and the user's recent
 * game sessions. Disables game launch buttons when `$aiReady` is false.
 * All POST forms include CSRF tokens and dynamic text is escaped via `e()`.
 *
 * @var list<array{game_type:string,status:string,player_score:int|float|string,ai_score:int|float|string,result:string,started_at:string,id:int}> $games
 * @var bool $aiReady
 * @var string $surpriseType
 * @var array{label:string,prompt:string,emoji:string} $surprisePrompt
 */
?>
<section class="pg-intro">
    <span class="eyebrow">JOKÉ · ENGLISH PLAYGROUND</span>
    <h1>Play with Baby Joke <span class="chibi chibi-happy chibi-inline" aria-hidden="true"></span></h1>
    <p>Pick a game, chat with our little bunny and collect some laughs.</p>
</section>

<div class="pg-shell pg-home">
    <!-- Left sidebar: game mode launcher -->
    <aside class="pg-menu">
        <div class="pg-bunny chibi chibi-laugh" aria-hidden="true"></div>
        <h2>Baby Joke</h2>
        <p class="pg-menu-sub">What shall we play?</p>

        <?php foreach ([ // Define the three available AI mini-game modes
            ['word_chain', '🔗', 'Word Chain', 'Chain topic-related words'],
            ['punchline_guess', '🎯', 'Guess the Punchline', 'Solve the joke'],
            ['joke_battle', '🎤', 'Tell a Joke', 'Share your best joke'],
        ] as $item): ?>
            <!-- Game start form with CSRF token and game type -->
            <form method="post" action="<?= e(\App\Core\Url::to('/playground/start')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="type" value="<?= e($item[0]) ?>">
                <button
                    class="pg-mode"
                    type="submit"
                    <?= (!$aiReady) ? 'disabled title="AI API configuration required"' : '' ?>
                >
                    <strong><?= e($item[1] . ' ' . $item[2]) ?></strong>
                    <small><?= e($item[3]) ?></small>
                </button>
            </form>
        <?php endforeach; // End game mode loop ?>

        <a class="pg-restart" href="<?= e(\App\Core\Url::to('/playground')) ?>">↻ Choose another game</a>
    </aside>

    <!-- Right panel: random surprise challenge preview -->
    <section class="pg-chat" aria-label="Baby Joke chat">
        <div class="pg-chat-scroll">
            <div class="pg-bubble pg-ai">
                <span class="pg-speaker">
                    <span class="chibi chibi-happy chibi-speaker" aria-hidden="true"></span>Baby Joke · Random challenge
                </span>
                <!-- Display escaped surprise challenge label and prompt -->
                <strong><?= e($surprisePrompt['emoji'] . ' ' . $surprisePrompt['label']) ?></strong>
                <p><?= e($surprisePrompt['prompt']) ?></p>

                <!-- Quick-launch form for the displayed surprise game -->
                <form method="post" action="<?= e(\App\Core\Url::to('/playground/start')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                    <input type="hidden" name="type" value="<?= e($surpriseType) ?>">
                    <button class="pg-surprise-button" type="submit" <?= (!$aiReady) ? 'disabled' : '' ?>>
                        Play this surprise game →
                    </button>
                </form>

                <small>Refresh this page for another random challenge, or choose any game on the left.</small>
            </div>
        </div>

        <div class="pg-composer pg-composer-idle">
            <span>Choose a game to start chatting...</span>
            <button type="button" disabled>Send</button>
        </div>
    </section>
</div>

<?php if (!$aiReady): // Display notice when Gemini API key is not configured ?>
    <p class="pg-api-note">All three games require a Gemini API key.</p>
<?php endif; // End AI readiness check ?>

<!-- Recent game sessions (capped at 6) -->
<?php if ($games): // Render recent games list when user has previous sessions ?>
    <section class="pg-history">
        <h2>Recent games</h2>
        <div class="pg-history-list">
            <?php foreach (array_slice($games, 0, 6) as $g): // Iterate over up to 6 most recent sessions ?>
                <a href="<?= e(\App\Core\Url::to('/playground/' . $g['id'])) ?>">
                    <span><?= e(ucwords(str_replace('_', ' ', $g['game_type']))) ?></span>
                    <small><?= e($g['started_at']) ?></small>
                    <span>Open →</span>
                </a>
            <?php endforeach; // End recent games loop ?>
        </div>
    </section>
<?php endif; // End recent games check ?>
