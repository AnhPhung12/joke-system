<?php
/**
 * Admin Center Landing Template
 *
 * Provides quick-access navigation cards to the core administrative sections
 * (overview analytics, user management, categories, and system settings).
 * Protected by admin/staff permissions in the corresponding controller.
 */
?>
<!-- Admin dashboard heading -->
<div class="page-heading">
    <span class="eyebrow">ADMIN ONLY</span>
    <h1>Admin Center 👑</h1>
    <p>Welcome to your separate management area.</p>
</div>

<!-- Admin section shortcut cards with escaped internal route URLs -->
<div class="cards">
    <!-- Overview & analytics route -->
    <a class="card" href="<?= e(\App\Core\Url::to('/admin/overview')) ?>">
        <span class="eyebrow">ANALYTICS</span>
        <h3>Overview & Analytics</h3>
        <p>Real database statistics.</p>
    </a>

    <!-- User management route -->
    <a class="card" href="<?= e(\App\Core\Url::to('/admin/users')) ?>">
        <span class="eyebrow">MEMBERS</span>
        <h3>Manage Users</h3>
        <p>Roles and account status.</p>
    </a>

    <!-- Category taxonomy management route -->
    <a class="card" href="<?= e(\App\Core\Url::to('/admin/categories')) ?>">
        <span class="eyebrow">TAXONOMY</span>
        <h3>Categories</h3>
        <p>Create and edit categories.</p>
    </a>

    <!-- System settings route -->
    <a class="card" href="<?= e(\App\Core\Url::to('/admin/settings')) ?>">
        <span class="eyebrow">SYSTEM</span>
        <h3>Settings</h3>
        <p>Public settings and AI status.</p>
    </a>
</div>
