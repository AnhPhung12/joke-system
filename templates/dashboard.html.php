<?php
/**
 * User Dashboard Template
 *
 * Displays the authenticated user's account summary, assigned roles,
 * and active session status after login verification.
 * All dynamic user fields are escaped with `e()` to prevent XSS.
 */

use App\Core\Auth; // Import authentication helper

$user = Auth::user(); // Retrieve the currently authenticated user record
?>
<!-- Dashboard greeting header -->
<div class="page-heading">
    <span class="eyebrow">YOUR PERSONAL SPACE</span>
    <!-- Escape username before rendering -->
    <h1>Hey, <?= e($user['username']) ?>! 👋</h1>
    <p>Signed in successfully. Your account and permissions were verified using MySQL.</p>
</div>

<!-- Account summary cards -->
<div class="cards">
    <div class="card">
        <span class="eyebrow">MY ACCOUNT</span>
        <!-- Display escaped username and email -->
        <h3><?= e($user['username']) ?></h3>
        <p><?= e($user['email']) ?></p>
    </div>

    <div class="card">
        <span class="eyebrow">MY ROLES</span>
        <!-- Format and escape comma-separated user roles -->
        <h3><?= e(ucwords(implode(', ', $user['roles']))) ?></h3>
        <p>Access privileges enabled</p>
    </div>

    <div class="card">
        <span class="eyebrow">SESSION STATUS</span>
        <h3>Active</h3>
        <p>Authentication and security checks passed.</p>
    </div>
</div>
