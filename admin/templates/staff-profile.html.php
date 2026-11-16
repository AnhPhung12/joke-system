<?php
/**
 * Staff profile overview template for administrators and moderators.
 * Expects `$profile` (user row with `id`, `username`, `email`, `created_at`,
 * `avatar_path`, `has_avatar`, `status`, and optional `roles`).
 *
 * @var array $profile
 */

use App\Core\{Auth, Url}; // Import authentication and URL helpers.

// Determine display role label and whether a preset avatar is selected.
$staffRoles = $profile['roles'] ?? Auth::user()['roles'] ?? []; // Gather assigned roles from profile or active session.
$role = in_array('admin', $staffRoles, true) ? 'Administrator' : 'Moderator'; // Resolve human-readable staff role label.
$preset = 0; // Initialize preset avatar index as unset.
if (preg_match('/^preset:(1[0-2]|[1-9])$/', (string) ($profile['avatar_path'] ?? ''), $m)) { // Match built-in preset avatar identifiers (1-12).
    $preset = (int) $m[1]; // Store matched preset index.
}
?>
<!-- Staff profile header -->
<div class="staff-profile-header">
  <div class="eyebrow">STAFF ACCOUNT</div>
  <h1><?= e($role) ?> Profile</h1>
  <p>Manage your identity, account security and access to the JOKÉ Staff Workspace.</p>
</div>

<!-- Staff summary card with avatar and edit shortcut -->
<section class="staff-profile-card">
  <div class="staff-profile-avatar">
    <?php if ($preset): ?>
      <span class="preset-avatar preset-<?= $preset ?>" aria-hidden="true"></span>
    <?php elseif (!empty($profile['has_avatar'])): ?>
      <img src="<?= e(Url::base() . '/avatar.php?id=' . (int) $profile['id']) ?>" alt="Staff avatar">
    <?php else: ?>
      <?= e(mb_strtoupper(mb_substr($profile['username'], 0, 1))) ?>
    <?php endif; ?>
  </div>
  <div>
    <span class="staff-role-badge"><?= e($role) ?></span>
    <h2><?= e($profile['username']) ?></h2>
    <p><?= e($profile['email']) ?></p>
    <p>Member since <?= e(substr((string) $profile['created_at'], 0, 10)) ?></p>
  </div>
  <a class="btn primary" href="<?= e(Url::to('/profile/settings')) ?>">Edit Staff Profile</a>
</section>

<div class="staff-profile-grid">
  <section class="panel">
    <h2>Account information</h2>
    <p><strong>Username:</strong> <?= e($profile['username']) ?></p>
    <p><strong>Email:</strong> <?= e($profile['email']) ?></p>
    <p><strong>Status:</strong> <?= e($profile['status'] ?? 'active') ?></p>
  </section>
  <section class="panel">
    <h2>Staff access</h2>
    <p>Access to management modules is controlled by your assigned role and permissions.</p>
    <a class="btn outline" href="<?= e(Url::to('/admin/overview')) ?>">Return to Staff Panel</a>
  </section>
</div>
