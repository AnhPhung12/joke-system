<?php
/**
 * Profile Settings Template
 *
 * Allows the signed-in user to manage their avatar (custom upload with client-side
 * circular crop modal or 12 preset characters), update public profile details,
 * request a password-change OTP, or deactivate their account.
 * All POST forms include CSRF tokens and dynamic output is escaped via `e()`.
 *
 * @var array $profile
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers

// Resolve active preset avatar index (1..12) if configured
$preset = 0; // Default to no preset selected
if (preg_match('/^preset:(1[0-2]|[1-9])$/', (string)($profile['avatar_path'] ?? ''), $m)) { // Match preset:1 through preset:12
    $preset = (int)$m[1]; // Extract integer preset ID
}

// Format account join date and time safely
$rawJoined = (string)($profile['created_at'] ?? ''); // Read raw creation timestamp
$joinedDate = $rawJoined !== '' ? date('Y-m-d', strtotime($rawJoined)) : '—'; // Format join date or fallback dash
$joinedTime = $rawJoined !== '' ? date('H:i:s', strtotime($rawJoined)) : '—'; // Format join time or fallback dash
?>
<!-- Scoped styles for the avatar crop dialog and settings avatar framing -->
<style>
.settings-avatar .avatar-photo {
    object-position: center 72%;
    background: var(--mint);
}
.avatar-crop-dialog {
    width: min(94vw, 580px);
    margin: auto;
    padding: 0;
    border: 0;
    border-radius: 28px;
    background: transparent;
    box-shadow: 0 24px 72px rgba(70, 42, 35, .3);
}
.avatar-crop-dialog::backdrop {
    background: rgba(45, 30, 34, .62);
    backdrop-filter: blur(4px);
}
.avatar-crop-panel {
    padding: 26px;
    background: #fffaf5;
    border-radius: 28px;
    color: #55372c;
}
.avatar-crop-panel h2 {
    margin: 0 0 5px;
}
.avatar-crop-panel p {
    margin: 0 0 20px;
    color: #78645d;
}
.avatar-crop-stage {
    display: grid;
    place-items: center;
    padding: 12px;
    margin: 0 auto 18px;
    overflow: hidden;
    border-radius: 24px;
    background: linear-gradient(135deg, #dff8f4, #fff1e8);
}
#avatar-crop-canvas {
    display: block;
    width: min(76vw, 400px);
    height: min(76vw, 400px);
    max-height: 400px;
    border-radius: 50%;
    cursor: grab;
    touch-action: none;
    box-shadow: 0 12px 28px rgba(30, 105, 100, .2);
}
#avatar-crop-canvas.is-dragging {
    cursor: grabbing;
}
.avatar-crop-zoom {
    display: flex;
    gap: 12px;
    align-items: center;
    margin: 0 0 20px;
    font-weight: 700;
}
.avatar-crop-zoom input {
    flex: 1;
    accent-color: #43bdb5;
}
.avatar-crop-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.avatar-crop-actions .btn {
    min-width: 120px;
}
@media (max-width: 520px) {
    .avatar-crop-panel {
        padding: 20px;
    }
    .avatar-crop-actions {
        justify-content: stretch;
    }
    .avatar-crop-actions .btn {
        flex: 1;
        padding: 12px 8px;
    }
}
</style>

<!-- Client-side circular avatar crop modal (JS hooks: #avatar-crop-dialog, #avatar-crop-canvas, #avatar-crop-zoom, #avatar-crop-cancel, #avatar-crop-save) -->
<dialog id="avatar-crop-dialog" class="avatar-crop-dialog" aria-labelledby="avatar-crop-title">
    <div class="avatar-crop-panel">
        <h2 id="avatar-crop-title">Adjust your photo</h2>
        <p>Drag the photo and use the slider so your face fits the circle.</p>
        <div class="avatar-crop-stage">
            <canvas id="avatar-crop-canvas" width="800" height="800" aria-label="Photo crop preview"></canvas>
        </div>
        <label class="avatar-crop-zoom" for="avatar-crop-zoom">
            Zoom <input id="avatar-crop-zoom" type="range" min="1" max="3" value="1" step="0.01">
        </label>
        <div class="avatar-crop-actions">
            <button class="btn ghost" type="button" id="avatar-crop-cancel">Cancel</button>
            <button class="btn primary" type="button" id="avatar-crop-save">Use this photo</button>
        </div>
    </div>
</dialog>

<div class="settings-page">
    <header class="settings-hero">
        <div>
            <span class="eyebrow">ACCOUNT SETTINGS</span>
            <h1>Make JOKÉ yours</h1>
            <p>Update your public profile, secure your account and control your access.</p>
        </div>
        <a class="btn ghost" href="<?= e(Url::to('/profile')) ?>">← Back to profile</a>
    </header>

    <!-- Current account identity summary -->
    <section class="settings-identity">
        <div class="settings-avatar">
            <?php if ($preset): // Render selected preset character avatar ?>
                <span class="preset-avatar preset-<?= $preset ?>" aria-hidden="true"></span>
            <?php elseif (!empty($profile['has_avatar'])): // Render custom uploaded avatar image ?>
                <img class="avatar-photo" src="<?= e(Url::base() . "/avatar.php?id=" . (int)$profile["id"]) ?>" alt="Profile photo">
            <?php else: // Fallback to uppercase first letter of username ?>
                <?= e(mb_strtoupper(mb_substr($profile['username'], 0, 1))) ?>
            <?php endif; // End avatar display check ?>
        </div>

        <div>
            <span class="settings-kicker">YOUR ACCOUNT</span>
            <h2><?= e($profile['username']) ?></h2>
            <p><?= e($profile['email']) ?></p>

            <div class="settings-joined-meta">
                <span class="joined-badge">
                    <svg class="joined-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><strong>Date joined:</strong> <?= e($joinedDate) ?></span>
                </span>
                <span class="joined-badge">
                    <svg class="joined-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="13" height="13" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span><strong>Time joined:</strong> <?= e($joinedTime) ?></span>
                </span>
                <?php if (!empty($profile['location'])): // Display location badge only when location is set ?>
                    <span class="joined-badge">
                        <span aria-hidden="true">📍</span>
                        <span><strong>Location:</strong> <?= e($profile['location']) ?></span>
                    </span>
                <?php endif; // End location badge check ?>
            </div>
        </div>

        <button
            class="btn outline"
            type="button"
            data-toggle-avatar-picker
            aria-expanded="false"
            aria-controls="avatar-picker"
        >
            Change photo
        </button>
    </section>

    <!-- Collapsible avatar picker (upload + 12 preset avatars) -->
    <section class="avatar-picker" id="avatar-picker" hidden>
        <div class="avatar-picker-head">
            <div>
                <span class="settings-kicker">CHOOSE YOUR LOOK</span>
                <h2>Pick an avatar</h2>
                <p>Choose a JOKÉ character or upload your own image.</p>
            </div>
            <button class="avatar-picker-close" type="button" data-toggle-avatar-picker aria-label="Close avatar picker">×</button>
        </div>

        <div class="avatar-choice-grid">
            <!-- Custom image upload form with CSRF token -->
            <form
                method="post"
                action="<?= e(Url::to('/profile/avatar')) ?>"
                enctype="multipart/form-data"
                class="avatar-choice avatar-upload-choice"
            >
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label title="Upload your own photo">
                    <span class="avatar-upload-plus">+</span>
                    <span>Upload your photo</span>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-avatar-upload required>
                </label>
            </form>

            <?php for ($i = 1; $i <= 12; $i++): // Render 12 preset avatar selection forms ?>
                <form method="post" action="<?= e(Url::to('/profile/avatar/preset')) ?>" class="avatar-choice">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="preset" value="<?= $i ?>">
                    <button
                        type="submit"
                        class="preset-option <?= $preset === $i ? 'is-selected' : '' ?>"
                        aria-label="Choose preset avatar <?= $i ?>"
                    >
                        <span class="preset-avatar preset-<?= $i ?>" aria-hidden="true"></span>
                    </button>
                </form>
            <?php endfor; // End preset avatar loop ?>
        </div>
    </section>

    <!-- Profile details & password security cards -->
    <div class="settings-grid">
        <section class="settings-card">
            <div class="settings-card-icon">✦</div>
            <div>
                <span class="settings-kicker">PROFILE</span>
                <h2>Personal details</h2>
                <p>This is how the JOKÉ community sees you.</p>
            </div>
            <!-- Profile update form with CSRF protection -->
            <form method="post" action="<?= e(Url::to('/profile')) ?>" class="stacked-form">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <label>
                    Username
                    <input name="username" maxlength="40" required value="<?= e($profile['username']) ?>">
                </label>
                <label>
                    Location
                    <input name="location" maxlength="100" placeholder="e.g. London, United Kingdom" value="<?= e($profile['location'] ?? '') ?>">
                </label>
                <label>
                    Bio
                    <textarea name="bio" maxlength="500" placeholder="Tell the community a little about you..."><?= e($profile['bio'] ?? '') ?></textarea>
                </label>
                <button class="btn primary">Save profile</button>
            </form>
        </section>

        <section class="settings-card security-card">
            <div class="settings-card-icon">⌁</div>
            <div>
                <span class="settings-kicker">SECURITY</span>
                <h2>Change password</h2>
                <p>We will send a four-digit verification code to <strong><?= e($profile['email']) ?></strong> before allowing a password change.</p>
            </div>
            <!-- Password change OTP request form with CSRF token -->
            <form method="post" action="<?= e(Url::to('/profile/password/otp')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <button class="btn outline">Send security code</button>
            </form>
        </section>
    </div>

    <!-- Account deactivation danger zone -->
    <section class="settings-card danger-card">
        <div class="settings-card-icon">!</div>
        <div>
            <span class="settings-kicker">DANGER ZONE</span>
            <h2>Deactivate account</h2>
            <p>This signs you out, disables access and anonymises personal information. This action cannot be undone through the website.</p>
        </div>
        <!-- Account deactivation form requiring password, confirmation checkbox, and CSRF token -->
        <form
            method="post"
            action="<?= e(Url::to('/profile/delete')) ?>"
            data-confirm="Are you sure you want to deactivate your account? This cannot be undone through the website."
            class="danger-form"
        >
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <label>
                Enter your password to confirm
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <label class="delete-confirm">
                <input type="checkbox" name="confirm_delete" value="yes" required> I understand the consequences.
            </label>
            <button class="btn danger" type="submit">Deactivate account</button>
        </form>
    </section>
</div>
