<?php
/**
 * Master Application Layout Template
 *
 * Wraps every rendered view (`$contentFile`) with the HTML5 document shell,
 * W3.CSS and custom stylesheets, deferred JS bundles, role-aware sidebar
 * navigation (user vs. staff workspace), notification bell dropdown, flash
 * banner, and site footer.
 *
 * The layout is a template, not a controller; all dynamic values are escaped
 * via `e()`, and all state-changing forms include CSRF tokens.
 *
 * @var string $viewName
 * @var string|null $title
 * @var string $contentFile
 */

use App\Core\{Auth, Csrf, Session, Url}; // Import core auth, CSRF, session, and URL helpers

$isStaffUser = Auth::hasRole("admin") || Auth::hasRole("moderator"); // Check if current user is admin or moderator
$isAdminLayout = str_starts_with($viewName, "admin/") || $isStaffUser; // Use staff layout for admin views or staff users
$pendingStaffCount = 0; // Initialize pending moderation/report badge count

// Count pending jokes and open reports for the staff notification dot
if (Auth::user() && $isStaffUser) { // Query badge counts only for authenticated staff
    try {
        $db = \App\Core\Database::connect(); // Obtain PDO database connection
        $pj = (int)$db->query("SELECT COUNT(*) FROM jokes WHERE status='pending'")->fetchColumn(); // Count jokes awaiting approval
        $pr = (int)$db->query("SELECT COUNT(*) FROM reports WHERE status IN ('pending','reviewing')")->fetchColumn(); // Count unresolved reports
        $pendingStaffCount = $pj + $pr; // Sum total pending staff items
    } catch (\Throwable $e) {
        $pendingStaffCount = 0; // Fail safely with zero badge count on DB error
    }
}

$currentUser = Auth::user(); // Fetch currently authenticated user array or null
$flash = Session::consumeFlash(); // Retrieve and clear one-time flash message
$bellItems = $currentUser ? (new \App\Models\Profile())->notifications((int)$currentUser["id"]) : []; // Load notifications for signed-in user
$bellUnread = count(array_filter($bellItems, static fn($n) => !(bool)$n["is_read"])); // Count unread notifications
?>
<!doctype html>
<html lang="<?= e(joke_lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title ?? 'JOKÉ') ?></title>

    <!-- W3.CSS framework + application stylesheets -->
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/chibi.css')) ?>?v=4">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/auth.css')) ?>?v=20261116">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/profile.css')) ?>?v=20261114q">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/admin.css')) ?>?v=20261114q">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/playground.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::to('/assets/css/language-profile.css')) ?>?v=20261114q">

    <!-- Deferred client scripts -->
    <script src="<?= e(Url::to('/assets/js/main.js')) ?>?v=20261116" defer></script>
    <script src="<?= e(Url::to('/assets/js/staff-admin.js')) ?>?v=20261114q" defer></script>
</head>
<body class="<?= $isAdminLayout ? 'admin-area' : 'user-area' ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <div class="shell">
        <!-- Left sidebar navigation -->
        <aside class="sidebar">
            <a class="logo" href="<?= e(Url::to($isAdminLayout ? '/admin/overview' : '/')) ?>">
                JOK<span>É</span><span class="logo-dot">●</span>
            </a>
            <div class="side-caption"><?= $isAdminLayout ? "STAFF MANAGEMENT PANEL" : "YOUR HAPPY CORNER" ?></div>

            <nav class="side-nav">
                <?php if ($isAdminLayout): // Render staff management navigation links ?>
                    <?php foreach ([ // Define permission-gated staff sidebar routes
                        ['analytics.view', '/admin/overview', '▥', 'Analytics'],
                        ['users.view', '/admin/users', '♙', 'Users'],
                        ['categories.manage', '/admin/categories', '◈', 'Categories'],
                        ['settings.manage', '/admin/settings', '⚙', 'Settings'],
                        ['jokes.manage', '/admin/jokes', '☰', 'Manage Jokes'],
                        ['reports.manage', '/admin/reports', '⚑', 'Reports'],
                    ] as $link): ?>
                        <?php if (Auth::can($link[0])): // Show link only if user holds required capability ?>
                            <a href="<?= e(Url::to($link[1])) ?>">
                                <span><?= e($link[2]) ?> &nbsp; <?= e($link[3]) ?></span>
                                <?php if ($link[1] === '/admin/reports' && $pendingStaffCount > 0): // Show pending badge on Reports link ?>
                                    <span class="nav-red-dot" title="<?= (int)$pendingStaffCount ?> pending for review" aria-label="<?= (int)$pendingStaffCount ?> pending items">
                                        <span class="nav-dot-glow"></span>
                                        <span class="nav-dot-count"><?= (int)$pendingStaffCount ?></span>
                                    </span>
                                <?php endif; // End pending badge check ?>
                            </a>
                        <?php endif; // End capability check ?>
                    <?php endforeach; // End staff nav loop ?>
                <?php else: // Render standard user/guest navigation links ?>
                    <a href="<?= e(Url::to('/')) ?>">⌂ &nbsp; Home</a>
                    <a href="<?= e(Url::to('/jokes')) ?>">☁ &nbsp; Joke Library</a>
                    <?php if ($currentUser): // Show authenticated user routes ?>
                        <a href="<?= e(Url::to('/my-jokes')) ?>">✎ &nbsp; My Jokes</a>
                        <a href="<?= e(Url::to('/jokes/create')) ?>">＋ &nbsp; Create Joke</a>
                        <a href="<?= e(Url::to('/playground')) ?>">✦ &nbsp; AI Playground</a>
                        <?php if (Auth::can('admin.view')): // Show staff panel shortcut for authorized users ?>
                            <a href="<?= e(Url::to('/admin/overview')) ?>">
                                <span>▦ &nbsp; Staff Panel</span>
                                <?php if ($pendingStaffCount > 0): // Show pending review indicator ?>
                                    <span class="nav-red-dot" title="<?= (int)$pendingStaffCount ?> pending for review">
                                        <span class="nav-dot-glow"></span>
                                        <span class="nav-dot-count"><?= (int)$pendingStaffCount ?></span>
                                    </span>
                                <?php endif; // End pending indicator check ?>
                            </a>
                        <?php endif; // End admin.view capability check ?>
                    <?php endif; // End authenticated user nav check ?>
                <?php endif; // End staff vs. user nav branch ?>
            </nav>

            <!-- Sidebar account footer -->
            <div class="sidebar-bottom">
                <?php if ($currentUser): // Show signed-in user profile card ?>
                    <a class="sidebar-account" href="<?= e(Url::to('/profile')) ?>" aria-label="View profile <?= e($currentUser['username']) ?>">
                        <span class="account-avatar">
                            <?php if (preg_match('/^preset:(1[0-2]|[1-9])$/', (string)($currentUser['avatar_path'] ?? ''), $presetMatch)): // Render preset avatar if matched ?>
                                <span class="preset-avatar preset-<?= (int)$presetMatch[1] ?>" aria-hidden="true"></span>
                            <?php elseif (!empty($currentUser["has_avatar"])): // Render uploaded avatar image ?>
                                <img src="<?= e(Url::base() . "/avatar.php?id=" . (int)$currentUser["id"]) ?>" alt="" class="avatar-img">
                            <?php else: // Fallback to uppercase initial ?>
                                <?= e(mb_strtoupper(mb_substr($currentUser["username"], 0, 1))) ?>
                            <?php endif; // End sidebar avatar branch ?>
                        </span>
                        <span class="account-name">
                            <strong><?= e($currentUser['username']) ?></strong>
                            <small><?= $isAdminLayout ? "Staff profile" : e(tr("View profile")) ?> →</small>
                        </span>
                        <span aria-hidden="true">⚙</span>
                    </a>
                <?php else: // Show guest sign-in prompt ?>
                    <a class="sidebar-account" href="<?= e(Url::to('/login')) ?>">
                        <span class="account-avatar">☺</span>
                        <span class="account-name">
                            <strong>Guest</strong>
                            <small>Sign in →</small>
                        </span>
                    </a>
                <?php endif; // End sidebar account branch ?>
            </div>
        </aside>

        <!-- Main workspace column -->
        <div class="workspace">
            <header class="topbar">
                <div class="top-title">
                    <?= $isAdminLayout ? "JOKÉ · Staff Workspace" : "A little place for big laughs ✨" ?>
                </div>

                <div class="top-actions">
                    <?php if ($currentUser): // Show notification bell and logout controls for signed-in users ?>
                        <!-- Notification bell dropdown (JS hooks: #bell-trigger, #bell-panel) -->
                        <div class="bell-wrap">
                            <button
                                class="notice-link bell-trigger"
                                type="button"
                                id="bell-trigger"
                                aria-controls="bell-panel"
                                aria-expanded="false"
                                aria-label="<?= e(tr('Notifications')) ?>"
                            >
                                🔔<?php if ($bellUnread): // Render unread count badge when > 0 ?><span class="bell-count">+<?= (int)$bellUnread ?></span><?php endif; ?>
                            </button>

                            <section class="bell-panel" id="bell-panel" hidden aria-label="<?= e(tr('Notifications')) ?>">
                                <div class="bell-panel-head">
                                    <strong><?= e(tr('Notifications')) ?></strong>
                                    <!-- Mark all notifications as read form with CSRF token -->
                                    <form method="post" action="<?= e(Url::to('/notifications/read-all')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                        <button class="bell-readall" type="submit"><?= e(tr('Mark all as read')) ?></button>
                                    </form>
                                </div>

                                <div class="bell-list">
                                    <?php if (!$bellItems): // Show empty state when user has no notifications ?>
                                        <p class="bell-empty"><?= e(tr('No notifications yet.')) ?></p>
                                    <?php endif; ?>

                                    <?php foreach ($bellItems as $notice): // Render each notification item ?>
                                        <article class="bell-item <?= $notice['is_read'] ? '' : 'unread' ?>">
                                            <div>
                                                <strong><?= e($notice['title']) ?></strong>
                                                <p><?= e($notice['message']) ?></p>
                                                <small><?= e($notice['created_at']) ?></small>
                                            </div>
                                            <?php if (!$notice['is_read']): // Show single-item mark-read form if unread ?>
                                                <form method="post" action="<?= e(Url::to('/notifications/' . (int)$notice['id'] . '/read')) ?>">
                                                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                                    <button class="bell-read" aria-label="<?= e(tr('Mark read')) ?>">✓</button>
                                                </form>
                                            <?php endif; // End unread item check ?>
                                        </article>
                                    <?php endforeach; // End notification items loop ?>
                                </div>
                            </section>
                        </div>

                        <span class="hello">Hi, <?= e($currentUser['username']) ?>!</span>
                        <!-- Logout form with CSRF protection -->
                        <form method="post" action="<?= e(Url::to('/logout')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                            <button class="btn ghost" type="submit"><?= e(tr("Logout")) ?></button>
                        </form>
                    <?php else: // Guest login and registration links ?>
                        <a class="btn ghost" href="<?= e(Url::to('/login')) ?>"><?= e(tr("Login")) ?></a>
                        <a class="btn primary" href="<?= e(Url::to('/register')) ?>">Join JOKÉ</a>
                    <?php endif; // End top-actions auth check ?>
                </div>
            </header>

            <!-- Active view content -->
            <main id="main-content" class="content">
                <?php if ($flash): // Render one-time flash notification banner if present ?>
                    <div class="flash" role="status"><?= e($flash) ?></div>
                <?php endif; ?>

                <?php require $contentFile; // Include the resolved page template ?>
            </main>

            <footer class="footer site-footer">
                <div class="footer-brand">
                    <a class="footer-logo" href="<?= e(Url::to('/')) ?>">JOK<span>É</span></a>
                    <p>A little place for big laughs.</p>
                </div>

                <nav class="footer-links" aria-label="Footer navigation">
                    <a href="<?= e(Url::to('/')) ?>">Home</a>
                    <a href="<?= e(Url::to('/jokes')) ?>">Joke Community</a>
                    <?php if ($currentUser): // Show authenticated footer links ?>
                        <a href="<?= e(Url::to('/playground')) ?>">AI Playground</a>
                        <a href="<?= e(Url::to('/profile')) ?>">My profile</a>
                    <?php else: // Show guest footer links ?>
                        <a href="<?= e(Url::to('/login')) ?>">Sign in</a>
                        <a href="<?= e(Url::to('/register')) ?>">Join JOKÉ</a>
                    <?php endif; // End footer auth branch ?>
                </nav>

                <div class="footer-note">
                    <!-- Dynamic copyright year -->
                    <span>© <?= date('Y') ?> JOKÉ</span>
                    <span>Made for smiles ✦</span>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>
