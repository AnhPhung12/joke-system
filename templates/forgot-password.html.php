<?php
/**
 * Forgot Password Template
 *
 * Renders the email submission form to initiate a 4-digit OTP password reset
 * handled by the authentication controller (`POST /forgot-password`).
 * Uses W3.CSS `.w3-card-4` styling and includes a CSRF token.
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- Password reset request card layout -->
<section class="auth-wrap w3-card-4">
    <div class="auth-art">
        <div class="mascot small">✉</div>
        <h2>Reset access</h2>
        <p>Enter the email linked to your account.</p>
    </div>

    <!-- Password reset request form with escaped target URL and CSRF protection -->
    <form class="auth-card" method="post" action="<?= e(Url::to('/forgot-password')) ?>">
        <span class="eyebrow">PASSWORD RESET</span>
        <h1>Forgot password?</h1>
        <p>A four-digit code will be sent if the account is eligible.</p>

        <!-- Hidden CSRF token field to prevent cross-site request forgery -->
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

        <label>
            Email
            <input name="email" type="email" maxlength="190" autocomplete="email" required>
        </label>

        <button class="btn primary full" type="submit">Send reset code</button>

        <!-- Link back to the login page -->
        <p class="form-foot">
            <a href="<?= e(Url::to('/login')) ?>">Back to sign in</a>
        </p>
    </form>
</section>
