<?php
/**
 * Password Reset Template (OTP Verification & New Password)
 *
 * Two-stage password reset view controlled by `$stage` (`'otp'` or password
 * entry). Stage `'otp'` renders a 4-digit OTP input with a live expiration
 * countdown (`$expiresAt`) and auto-submit script; the second stage renders
 * the new password form. Uses W3.CSS `.w3-card-4` and CSRF tokens.
 *
 * @var string $stage
 * @var int $expiresAt
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
?>
<!-- Two-stage password reset card layout -->
<section class="auth-wrap w3-card-4">
    <!-- Dynamic artwork and heading based on current reset stage -->
    <div class="auth-art">
        <div class="mascot small"><?= $stage === 'otp' ? '🔢' : '🔐' ?></div>
        <h2><?= $stage === 'otp' ? 'Check your email' : 'Choose a new password' ?></h2>
        <p><?= $stage === 'otp' ? 'Enter the four-digit code before it expires.' : 'Your code has been verified.' ?></p>
    </div>

    <?php if ($stage === 'otp'): // Render OTP verification form when in the 'otp' stage ?>
        <!-- Step 2: 4-digit OTP verification form -->
        <form class="auth-card" id="otp-form" method="post" action="<?= e(Url::to('/reset-password/verify')) ?>">
            <span class="eyebrow">STEP 2 OF 3</span>
            <h1>Enter your code</h1>
            <!-- Live countdown element seeded with server-side Unix expiration timestamp -->
            <p class="otp-timer">
                Code expires in <strong id="otp-countdown" data-expires-at="<?= (int) $expiresAt ?>">10:00</strong>
            </p>

            <?php if (!empty($_SESSION['reset_local_otp'])): ?>
                <div style="margin:0.75rem 0 1rem;padding:0.75rem 1rem;border-radius:10px;background:rgba(255,209,102,0.14);border:1px solid rgba(255,209,102,0.4);color:#ffd166;font-size:0.9rem;text-align:center;">
                    Local Test Mode (mail not configured) — Your OTP: <strong style="font-size:1.1rem;letter-spacing:3px;"><?= e((string) $_SESSION['reset_local_otp']) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Hidden CSRF token and combined 4-digit OTP value -->
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="otp" id="otp-value">

            <!-- Four individual numeric input boxes for the OTP -->
            <div class="otp-inputs" aria-label="Four-digit verification code">
                <?php for ($i = 0; $i < 4; $i++): // Loop to render 4 single-digit OTP input fields ?>
                    <input
                        class="otp-digit"
                        inputmode="numeric"
                        pattern="[0-9]"
                        maxlength="1"
                        autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                        aria-label="Code digit <?= $i + 1 ?>"
                        required
                    >
                <?php endfor; // End OTP digit loop ?>
            </div>

            <button class="btn primary full" type="submit">Verify code</button>
            <!-- Link to request a fresh reset code -->
            <p class="form-foot">
                <a href="<?= e(Url::to('/forgot-password')) ?>">Send another code</a>
            </p>
        </form>
    <?php else: // Render new password entry form after OTP verification ?>
        <!-- Step 3: Set new password form -->
        <form class="auth-card" method="post" action="<?= e(Url::to('/reset-password')) ?>">
            <span class="eyebrow">STEP 3 OF 3</span>
            <h1>New password</h1>
            <p>Use at least 10 characters.</p>

            <!-- Hidden CSRF token field -->
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

            <label>
                New password
                <input name="password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required autofocus>
            </label>

            <label>
                Confirm new password
                <input name="confirm_password" type="password" minlength="10" maxlength="128" autocomplete="new-password" required>
            </label>

            <button class="btn primary full" type="submit">Save password</button>
        </form>
    <?php endif; // End reset stage conditional ?>
</section>

<?php if ($stage === 'otp'): // Include client-side OTP UX script only during OTP stage ?>
    <!-- OTP digit auto-advance, paste handling, auto-submit, and expiration countdown -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('otp-form');
        const digits = [...document.querySelectorAll('.otp-digit')];
        const value = document.getElementById('otp-value');
        const timer = document.getElementById('otp-countdown');
        const expires = Number(timer.dataset.expiresAt) * 1000;

        // Sync individual digit inputs into hidden field and auto-submit when 4 digits are entered
        const sync = () => {
            value.value = digits.map(input => input.value).join('');
            if (value.value.length === 4) form.requestSubmit();
        };

        // Attach input, backspace navigation, and paste handlers to each digit box
        digits.forEach((input, index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, 1);
                if (input.value && digits[index + 1]) digits[index + 1].focus();
                sync();
            });

            input.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && !input.value && digits[index - 1]) {
                    digits[index - 1].focus();
                }
            });

            input.addEventListener('paste', event => {
                event.preventDefault();
                const code = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 4);
                code.split('').forEach((digit, i) => digits[i].value = digit);
                digits[Math.min(code.length, 3)].focus();
                sync();
            });
        });

        // Update countdown timer every second and disable form when expired
        const tick = () => {
            const left = Math.max(0, expires - Date.now());
            const mins = Math.floor(left / 60000);
            const secs = Math.floor((left % 60000) / 1000);
            timer.textContent = `${mins}:${String(secs).padStart(2, '0')}`;
            if (left === 0) {
                digits.forEach(input => input.disabled = true);
                form.querySelector('button').disabled = true;
            }
        };

        tick();
        setInterval(tick, 1000);
        digits[0].focus();
    });
    </script>
<?php endif; // End OTP script conditional ?>
