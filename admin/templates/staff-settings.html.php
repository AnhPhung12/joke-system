<?php
/**
 * Staff profile settings template.
 * Expects `$profile` (current staff user row).
 * Provides forms for avatar upload, username/bio update, and password-reset OTP request.
 *
 * @var array $profile
 */

use App\Core\{Csrf, Url}; // Import CSRF token and URL helpers.
?>
<!-- Staff settings header and back link -->
<div class="staff-profile-header">
  <div class="eyebrow">STAFF SECURITY</div>
  <h1>Staff Profile Settings</h1>
  <p>Update your identity and protect password changes with an email code.</p>
  <a class="btn outline" href="<?= e(Url::to('/profile')) ?>">← Back to Staff Profile</a>
</div>

<div class="staff-profile-grid">
  <!-- Avatar upload form -->
  <section class="panel">
    <h2>Profile photo</h2>
    <form method="post" action="<?= e(Url::to('/profile/avatar')) ?>" enctype="multipart/form-data" class="stacked-form">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <label>
        Avatar (JPG, PNG or WebP, max 2 MB)
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
      </label>
      <button class="btn primary">Upload Avatar</button>
    </form>
  </section>

  <!-- Account details form -->
  <section class="panel">
    <h2>Account information</h2>
    <form method="post" action="<?= e(Url::to('/profile')) ?>" class="stacked-form">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <label>
        Username
        <input name="username" value="<?= e($profile['username']) ?>" maxlength="40" required>
      </label>
      <label>
        Bio
        <textarea name="bio" maxlength="500"><?= e($profile['bio'] ?? '') ?></textarea>
      </label>
      <button class="btn primary">Save Changes</button>
    </form>
  </section>

  <!-- Email OTP trigger for password change -->
  <section class="panel">
    <h2>Change password</h2>
    <p>A four-digit code will be sent to <?= e($profile['email']) ?>.</p>
    <form method="post" action="<?= e(Url::to('/profile/password/otp')) ?>">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <button class="btn outline">Send security code</button>
    </form>
  </section>
</div>
