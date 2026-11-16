<?php
/**
 * User Registration Template
 *
 * Renders the account creation form (`POST /register`) with client-side
 * username pattern, email, and password length constraints, plus CSRF
 * protection and a link to the sign-in page.
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- User registration card layout -->
<section class="auth-wrap">
    <div class="auth-art">
        <div class="mascot small">◕ᴗ◕</div>
        <h2>Join the fun!</h2>
        <p>Your happy corner starts here.</p>
    </div>

    <!-- Account registration form posting to /register with escaped action URL -->
    <form class="auth-card" method="post" action="<?= e(Url::to('/register')) ?>">
        <span class="eyebrow">CREATE AN ACCOUNT</span>
        <h1>Sign up</h1>
        <p>Join the JOKÉ community today.</p>

        <!-- Hidden CSRF token field to protect registration submission -->
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

        <label>
            Username
            <input
                name="username"
                minlength="3"
                maxlength="40"
                pattern="[A-Za-z0-9_]{3,40}"
                title="3–40 letters, numbers or underscores"
                autocomplete="username"
                required
            >
        </label>

        <label>
            Email
            <input name="email" type="email" maxlength="190" autocomplete="email" required>
        </label>

        <label>
            Password
            <input name="password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required>
        </label>

        <label>
            Confirm password
            <input name="confirm_password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required>
        </label>

        <button class="btn primary full" type="submit">Create Account →</button>

        <!-- Link to login page for existing users -->
        <p class="form-foot">
            Already have an account? <a href="<?= e(Url::to('/login')) ?>">Sign in</a>
        </p>
    </form>
</section>
