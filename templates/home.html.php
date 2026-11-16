<?php
/**
 * Home Page Template
 *
 * Renders the public landing hero with interactive chibi mood selector,
 * authentication-aware CTAs, and feature cards linking to the Joke Library,
 * AI Playground, and Daily Laugh surprise route.
 * Uses `tr()` for i18n and `e()` for XSS-safe output.
 */

use App\Core\{Url, Auth}; // Import URL and authentication helpers
?>
<!-- Public landing hero section -->
<section class="hero">
    <div>
        <!-- Translated hero eyebrow, headline, and subtitle -->
        <span class="eyebrow"><?= e(tr('WELCOME TO JOKÉ')) ?></span>
        <h1><?= e(tr('Make every day')) ?><br><em><?= e(tr('a little funnier.')) ?></em></h1>
        <p><?= e(tr('A friendly place to share jokes, make friends and play with AI. Explore stories, leave comments, save favourites and spread a little laughter.')) ?></p>

        <!-- Primary hero call-to-action buttons -->
        <div class="buttons">
            <a class="btn primary" href="<?= e(Url::to('/jokes')) ?>"><?= e(tr('Explore Jokes')) ?> →</a>
            <?php if (Auth::user()): // Show create button for signed-in users, otherwise login/register ?>
                <a class="btn outline" href="<?= e(Url::to('/jokes/create')) ?>"><?= e(tr('Create Joke')) ?> ＋</a>
            <?php else: // Guest call-to-action ?>
                <a class="btn outline" href="<?= e(Url::to('/login')) ?>"><?= e(tr('Login / Register')) ?></a>
            <?php endif; // End auth CTA branch ?>
        </div>
    </div>

    <!-- Interactive chibi mood selector (JS hooks: .hero-mood-face, [data-face], [data-bubble], [data-mood-bubble]) -->
    <div class="hero-blob">
        <div class="hero-mood" aria-label="Choose a chibi mood">
            <button class="hero-mood-face chibi chibi-laugh" data-face="chibi-laugh" data-bubble="Bright smile" type="button" aria-label="Bright smile"></button>
            <button class="hero-mood-face chibi chibi-sparkle" data-face="chibi-sparkle" data-bubble="Pleasantly surprised" type="button" aria-label="Pleasantly surprised"></button>
            <button class="hero-mood-face chibi chibi-blush" data-face="chibi-blush" data-bubble="Blushing smile" type="button" aria-label="Blushing smile"></button>
            <button class="hero-mood-face chibi chibi-sad chibi-sad-sleepy" data-face="chibi-sad chibi-sad-sleepy" data-bubble="Exhausted" type="button" aria-label="Exhausted"></button>
            <button class="hero-mood-face chibi chibi-sad chibi-sad-teary" data-face="chibi-sad chibi-sad-teary" data-bubble="Hurt" type="button" aria-label="Hurt"></button>
            <button class="hero-mood-face chibi chibi-sad chibi-sad-crying" data-face="chibi-sad chibi-sad-crying" data-bubble="Crying" type="button" aria-label="Crying"></button>
            <span class="chibi chibi-happy chibi-hero hero-mood-main" aria-hidden="true"></span>
            <!-- Default translated speech bubble text -->
            <span class="bubble" data-mood-bubble><?= e(tr('hee hee!')) ?></span>
        </div>
    </div>
</section>

<!-- Quick-access feature cards linking to jokes, playground, and random joke -->
<div class="cards home-cards">
    <a class="card card-link" href="<?= e(Url::to('/jokes')) ?>">
        <span class="eyebrow">COMMUNITY</span>
        <h3><?= e(tr('Joke Library')) ?> <span aria-hidden="true">→</span></h3>
        <p><?= e(tr('Browse, create, like, favourite, comment and reply to jokes.')) ?></p>
    </a>

    <a class="card card-link" href="<?= e(Url::to('/playground')) ?>">
        <span class="eyebrow">PLAYGROUND</span>
        <h3><?= e(tr('AI Games')) ?> <span aria-hidden="true">→</span></h3>
        <p><?= e(tr('Play Joke Battle and Word Chain in the AI Playground.')) ?></p>
    </a>

    <a class="card card-link" href="<?= e(Url::to('/jokes/surprise')) ?>">
        <span class="eyebrow">SURPRISE</span>
        <h3>Daily Laugh <span aria-hidden="true">→</span></h3>
        <p>Need a quick smile? Let JOKÉ surprise you with a joke.</p>
    </a>
</div>
