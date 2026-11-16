<?php
/**
 * User Registration Template
 *
 * Renders the account creation form (`POST /register`) with client-side
 * username pattern, email, and password length constraints, plus CSRF
 * protection and a link to the sign-in page.
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers
$isOtpStep = !empty($otpPending);
?>
<!-- User registration card layout -->
<section class="auth-wrap">
    <div class="auth-art">
        <div class="mascot small"><?= $isOtpStep ? '🔢' : '◕ᴗ◕' ?></div>
        <h2><?= $isOtpStep ? 'Verify your email' : 'Join the fun!' ?></h2>
        <p><?= $isOtpStep ? 'Enter the 4-digit code sent to your email.' : 'Your happy corner starts here.' ?></p>
    </div>

    <?php if ($isOtpStep): ?>
        <form class="auth-card" id="reg-otp-form" method="post" action="<?= e(Url::to('/register')) ?>">
            <span class="eyebrow">STEP 2 OF 2</span>
            <h1>Enter OTP code</h1>
            <p>We sent a 4-digit verification code to <strong><?= e((string) ($otpEmail ?? '')) ?></strong>.</p>
            <p class="otp-timer">
                Code expires in <strong id="reg-otp-countdown" data-expires-at="<?= (int) ($otpExpiresAt ?? 0) ?>">10:00</strong>
            </p>

            <?php if (!empty($localOtp)): ?>
                <div style="margin:0.75rem 0 1rem;padding:0.75rem 1rem;border-radius:10px;background:rgba(255,209,102,0.14);border:1px solid rgba(255,209,102,0.4);color:#7a5a00;font-size:0.9rem;text-align:center;">
                    Local Test Mode (mail not configured) — Your OTP: <strong style="font-size:1.1rem;letter-spacing:3px;"><?= e((string) $localOtp) ?></strong>
                </div>
            <?php endif; ?>

            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="otp" id="reg-otp-value">

            <div class="otp-inputs" aria-label="Four-digit verification code">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <input
                        class="otp-digit"
                        inputmode="numeric"
                        pattern="[0-9]"
                        maxlength="1"
                        autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                        aria-label="Code digit <?= $i + 1 ?>"
                        required
                    >
                <?php endfor; ?>
            </div>

            <button class="btn primary full" type="submit">Verify &amp; Create Account →</button>

            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:14px;">
                <button class="btn ghost" type="submit" name="action" value="resend_otp" formnovalidate style="margin-top:0;flex:1;">Resend code</button>
                <button class="btn ghost" type="submit" name="action" value="cancel_otp" formnovalidate style="margin-top:0;flex:1;">← Back</button>
            </div>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('reg-otp-form');
            const digits = [...form.querySelectorAll('.otp-digit')];
            const hidden = document.getElementById('reg-otp-value');
            const cd = document.getElementById('reg-otp-countdown');
            const sync = () => { hidden.value = digits.map(d => d.value.replace(/\D/g, '')).join(''); };

            digits.forEach((d, idx) => {
                d.addEventListener('input', () => {
                    d.value = d.value.replace(/\D/g, '').slice(-1);
                    sync();
                    if (d.value && idx < digits.length - 1) digits[idx + 1].focus();
                    if (hidden.value.length === 4) form.requestSubmit();
                });
                d.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && !d.value && idx > 0) digits[idx - 1].focus();
                });
                d.addEventListener('paste', e => {
                    const txt = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4);
                    if (!txt) return;
                    e.preventDefault();
                    txt.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
                    sync();
                    if (hidden.value.length === 4) form.requestSubmit();
                });
            });
            if (digits[0]) digits[0].focus();
            form.addEventListener('submit', sync);

            if (cd) {
                const exp = parseInt(cd.dataset.expiresAt || '0', 10);
                const tick = () => {
                    const left = Math.max(0, exp - Math.floor(Date.now() / 1000));
                    const m = String(Math.floor(left / 60)).padStart(2, '0');
                    const s = String(left % 60).padStart(2, '0');
                    cd.textContent = m + ':' + s;
                    if (left > 0) setTimeout(tick, 1000);
                };
                tick();
            }
        });
        </script>
    <?php else: ?>
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
            Location
            <input name="location" id="reg-location" maxlength="120" placeholder="e.g. Ho Chi Minh, Vietnam" value="Vietnam" required>
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
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const loc = document.getElementById('reg-location');
        if (!loc) return;
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            const map = {
                'Asia/Ho_Chi_Minh': 'Vietnam',
                'Asia/Saigon': 'Vietnam',
                'Asia/Bangkok': 'Thailand',
                'Asia/Tokyo': 'Japan',
                'Asia/Seoul': 'South Korea',
                'Asia/Singapore': 'Singapore',
                'Europe/London': 'United Kingdom',
                'Europe/Paris': 'France',
                'America/New_York': 'United States',
                'America/Los_Angeles': 'United States'
            };
            if (map[tz]) loc.value = map[tz];
        } catch (e) {}
    });
    </script>
    <?php endif; ?>
</section>
