<?php
/**
 * Moderator Dashboard Template
 *
 * Overview landing page for users with `moderation.view` permission.
 * Summarizes access to the pending joke queue and reported content sections.
 */
?>
<!-- Moderator workspace heading -->
<div class="page-heading">
    <span class="eyebrow">MODERATION WORKSPACE</span>
    <h1>Moderator Panel 🛡️</h1>
    <p>This page is protected by permissions <code>moderation.view</code>. Regular users will receive HTTP 403.</p>
</div>

<!-- Moderator summary cards -->
<div class="cards">
    <!-- Pending jokes summary -->
    <div class="card">
        <h3>Pending Jokes</h3>
        <p>Moderation queue is available in the review section.</p>
    </div>

    <!-- User reports summary -->
    <div class="card">
        <h3>Reports</h3>
        <p>Review reported content in the reports section.</p>
    </div>
</div>
