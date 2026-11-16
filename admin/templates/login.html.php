<?php
/**
 * Staff login template for administrators and moderators.
 * Submits credentials and CSRF token to `/staff/login`.
 */

use App\Core\{Csrf, Url}; // Import CSRF token and URL helpers.
?>
<!-- Staff login page wrapper -->
<section class="auth-wrap">
  <!-- Left branding and description panel -->
  <div class="auth-art">
    <div class="mascot small">⚙</div>
    <h2>JOKÉ Staff</h2>
    <p>Secure access for moderators and administrators.</p>
  </div>

  <!-- Staff authentication form with CSRF protection -->
  <form class="auth-card" method="post" action="<?= e(Url::to('/staff/login')) ?>">
    <span class="eyebrow">STAFF ACCESS</span>
    <h1>Admin / Moderator</h1>
    <p>Only authorised staff accounts can sign in here.</p>
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

    <label>
      Email
      <input type="email" name="email" required autocomplete="username">
    </label>
    <label>
      Password
      <input type="password" name="password" required autocomplete="current-password">
    </label>

    <button type="submit" class="btn primary full">Sign in to Staff Panel →</button>
    <p class="form-foot">
      <a href="<?= e(Url::to('/login')) ?>">← Client Login</a>
    </p>
  </form>
</section>
