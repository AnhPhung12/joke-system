<?php
/**
 * User Login Template
 *
 * Renders the standard email/password sign-in form (`POST /login`) with
 * W3.CSS `.w3-card-4` card styling, CSRF protection, and links to password
 * recovery, registration, and the staff login portal.
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- User login card layout -->
<section class="auth-wrap w3-card-4">
    <div class="auth-art">
        <span class="chibi chibi-happy chibi-auth" aria-hidden="true"></span>
        <h2>Welcome back!</h2>
        <p>Ready for another laugh?</p>
    </div>

    <!-- Standard login form posting to /login with escaped action URL -->
    <form class="auth-card" method="post" action="<?= e(Url::to('/login')) ?>">
        <span class="eyebrow">HELLO AGAIN</span>
        <h1>Sign in</h1>
        <p>Sign in to your JOKÉ account.</p>

        <!-- Hidden CSRF token field to protect form submission -->
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

        <label>
            Email
            <input name="email" type="email" maxlength="190" autocomplete="email" required>
        </label>

        <label>
            Password
            <input name="password" type="password" autocomplete="current-password" required>
        </label>

        <button class="btn primary full" type="submit">Login →</button>

        <!-- Navigation links for password recovery, registration, and staff portal -->
        <p class="form-foot">
            <a href="<?= e(Url::to('/forgot-password')) ?>">Forgot password?</a>
        </p>
        <p class="form-foot">
            Need an account? <a href="<?= e(Url::to('/register')) ?>">Sign up</a>
        </p>
        <p class="form-foot">
            <a href="<?= e(Url::to('/staff/login')) ?>">Admin / Moderator login →</a>
        </p>
    </form>
</section>
