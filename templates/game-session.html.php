<?php
/**
 * AI Playground Game Session Template
 *
 * Displays an active or completed AI game session (Word Chain, Guess the Punchline,
 * or Tell a Joke) with a sidebar mode switcher, chat transcript, and turn composer.
 * Rendered by the Playground controller; all output is escaped via `e()`.
 *
 * @var array{game_type:string,status:string,player_score:int|float|string,id:int,started_at?:string} $game
 * @var list<array{sender:string,content:string}> $messages
 */

$type = $game['game_type']; // Extract active game mode identifier
$modeNames = [ // Map internal game_type keys to display titles
    'word_chain'      => 'Word Chain',
    'punchline_guess' => 'Guess the Punchline',
    'joke_battle'     => 'Tell a Joke',
];
$modeIntro = [ // Map game_type keys to initial Baby Joke welcome instructions
    'word_chain'      => 'Topic Word Chain! Baby Joke chooses a topic and starting word. Enter one word starting with the last letter of the previous word. Stay on topic! Three mistakes and you lose the round. A new topic starts automatically! 🔗',
    'punchline_guess' => 'Welcome to Guess the Punchline! 🎯 Guess the punchline, or type I do not know to reveal the answer and move on.',
    'joke_battle'     => 'Tell me your funniest original joke! 🎤 I’ll rate it and try to make you laugh too.',
];
?>
<!-- Game session header with mode title, start time, and current score -->
<section class="pg-intro">
    <span class="eyebrow">JOKÉ · ENGLISH PLAYGROUND</span>
    <h1>
        <?= e($modeNames[$type] ?? 'Baby Joke') ?>
        <span class="chibi chibi-happy chibi-inline" aria-hidden="true"></span>
    </h1>
    <p>
        Session history · Started <?= e((string)($game['started_at'] ?? '')) ?> · Your score: <?= e((string)$game['player_score']) ?>
    </p>
</section>

<div class="pg-shell">
    <!-- Left sidebar: game mode switcher (CSRF-protected POST forms) -->
    <aside class="pg-menu">
        <div class="pg-bunny chibi chibi-excited" aria-hidden="true"></div>
        <h2>Baby Joke</h2>
        <p class="pg-menu-sub">What shall we play?</p>

        <?php foreach ([ // Render mode-switch buttons for all three AI games
            ['word_chain', '🔗', 'Word Chain', 'Chain topic-related words'],
            ['punchline_guess', '🎯', 'Guess the Punchline', 'Solve the joke'],
            ['joke_battle', '🎤', 'Tell a Joke', 'Share your best joke'],
        ] as $item): ?>
            <!-- Start new game session form with CSRF token -->
            <form method="post" action="<?= e(\App\Core\Url::to('/playground/start')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="type" value="<?= e($item[0]) ?>">
                <button class="pg-mode <?= $type === $item[0] ? 'active' : '' ?>" type="submit">
                    <strong><?= e($item[1] . ' ' . $item[2]) ?></strong>
                    <small><?= e($item[3]) ?></small>
                </button>
            </form>
        <?php endforeach; // End mode switcher loop ?>

        <a class="pg-restart" href="<?= e(\App\Core\Url::to('/playground')) ?>">↻ Start over / Choose game</a>
    </aside>

    <!-- Main chat area: message log and turn submission form -->
    <section class="pg-chat" aria-label="Game conversation">
        <div class="pg-chat-scroll" id="pg-chat-scroll" role="log" aria-live="polite">
            <?php if (!$messages): // Show fallback note for legacy sessions without turns ?>
                <div class="pg-bubble pg-ai">This older session has no saved turns yet.</div>
            <?php endif; ?>

            <!-- Always display the mode introduction bubble first -->
            <div class="pg-bubble pg-ai"><?= e($modeIntro[$type] ?? 'Let’s play!') ?></div>

            <?php foreach ($messages as $m): // Render each player and AI turn bubble ?>
                <div class="pg-bubble <?= $m['sender'] === 'ai' ? 'pg-ai' : 'pg-user' ?>">
                    <span class="pg-speaker">
                        <?php if ($m['sender'] === 'ai'): // Display AI speaker badge ?>
                            <span class="chibi chibi-happy chibi-speaker" aria-hidden="true"></span>Baby Joke
                        <?php else: // Display human player label ?>
                            You
                        <?php endif; // End speaker label branch ?>
                    </span>
                    <?= nl2br(e($m['content'])) ?>
                </div>
            <?php endforeach; // End chat transcript loop ?>

            <?php if ($game['status'] !== 'in_progress'): // Show final score summary once game ends ?>
                <div class="pg-bubble pg-ai">
                    Game over! Your score: <?= e((string)$game['player_score']) ?>. Thanks for playing! 🎉
                </div>
            <?php endif; // End game over check ?>
        </div>

        <?php if ($game['status'] === 'in_progress'): // Show turn input composer while session is active ?>
            <?php if ($type === 'punchline_guess'): // Show skip hint for Guess the Punchline mode ?>
                <div class="pg-punchline-help">
                    Stuck? Type <strong>I don't know</strong> to reveal the punchline and get a new question.
                </div>
            <?php elseif ($type === 'word_chain'): ?>
                <div class="pg-punchline-help">
                    Stuck? Type <strong>I don't know</strong> to forfeit this round and switch to a new topic.
                </div>
            <?php endif; ?>

            <!-- Turn submission form with CSRF protection -->
            <form class="pg-composer" method="post" action="<?= e(\App\Core\Url::to('/playground/' . $game['id'] . '/turn')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                <input
                    name="message"
                    maxlength="1000"
                    required
                    autocomplete="off"
                    autofocus
                    aria-label="Your message"
                    placeholder="<?= e($type === 'word_chain' ? 'Enter one word...' : ($type === 'punchline_guess' ? 'Type your punchline guess...' : 'Tell me a joke...')) ?>"
                >
                <button type="submit">Send ➜</button>
            </form>
        <?php else: // Show completed state bar with Play Again / New Topic link ?>
            <div class="pg-composer pg-composer-idle">
                <span>Game completed</span>
                <?php if ($type === 'word_chain'): ?>
                    <form method="post" action="<?= e(\App\Core\Url::to('/playground/start')) ?>" style="display:inline;margin:0">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
                        <input type="hidden" name="type" value="word_chain">
                        <button type="submit" class="btn primary" style="padding:8px 16px;font-size:14px">Play new topic ↻</button>
                    </form>
                <?php endif; ?>
                <a href="<?= e(\App\Core\Url::to('/playground')) ?>">Play again ↗</a>
            </div>
        <?php endif; // End active vs. completed composer branch ?>
    </section>
</div>

<!-- Keep chat transcript scrolled to the latest turn -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const el = document.getElementById('pg-chat-scroll');
        if (!el) return;
        const bottom = () => {
            el.scrollTop = el.scrollHeight;
        };
        bottom();
        requestAnimationFrame(bottom);
        window.addEventListener('load', bottom, {
            once: true
        });
    });
</script>
