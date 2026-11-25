<?php
/**
 * Admin users & staff management template.
 * Rendered by the `/admin/users` controller with `$users`, `$staffPermissions`,
 * `$permissionKeys`, and optional filter/sort state (`$q`, `$role`, `$sort`, `$filter_date`).
 * Supports column-level sorting/filtering, staff creation, editing, suspension,
 * moderator permission assignment, and permanent account deletion.
 *
 * @var array $users
 * @var array $staffPermissions
 * @var array $permissionKeys
 * @var string|null $q
 * @var string|null $role
 * @var string|null $sort
 * @var string|null $filter_date
 */

use App\Core\{Auth, Csrf, Url}; // Import auth, CSRF token, and URL helpers.

// Resolve active search, role filter, status filter, sort, and date filter from controller or query string.
$currentQ = $q ?? (string) ($_GET['q'] ?? ''); // Active search keyword.
$currentRole = $role ?? (string) ($_GET['filter_role'] ?? $_GET['role'] ?? ''); // Active role filter slug.
$currentStatus = $filter_status ?? (string) ($_GET['filter_status'] ?? $_GET['status'] ?? ''); // Active status filter slug.
$currentSort = $sort ?? (string) ($_GET['sort'] ?? ''); // Active column sort order.
$currentDate = $filter_date ?? (string) ($_GET['filter_date'] ?? $_GET['date'] ?? ''); // Active creation date filter.
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">STAFF &amp; MEMBERS</span>
  <h1>Users</h1>
  <p>View members, manage staff and assign moderator permissions.</p>
</div>

<!-- Account management table panel -->
<section class="panel staff-table-panel">
  <div class="staff-panel-heading">
    <div>
      <h2>Account Management</h2>
      <p>Administrators can manage staff and suspend or restore regular members.</p>
    </div>
    <?php if (Auth::can('users.manage')): // Show Add Staff button for users with management permission. ?>
      <button type="button" class="btn primary" data-open-dialog="staff-create">+ Add Staff</button>
    <?php endif; // End Add Staff button check. ?>
  </div>

  <!-- Search bar preserving active column filters and sort order -->
  <form class="filters staff-search" method="get" action="<?= e(Url::to('/admin/users')) ?>">
    <input name="q" placeholder="Search username or email" value="<?= e($currentQ) ?>">
    <?php if ($currentRole !== ''): ?>
      <input type="hidden" name="filter_role" value="<?= e($currentRole) ?>">
    <?php endif; ?>
    <?php if ($currentStatus !== ''): ?>
      <input type="hidden" name="filter_status" value="<?= e($currentStatus) ?>">
    <?php endif; ?>
    <?php if ($currentDate !== ''): ?>
      <input type="hidden" name="filter_date" value="<?= e($currentDate) ?>">
    <?php endif; ?>
    <?php if ($currentSort !== ''): ?>
      <input type="hidden" name="sort" value="<?= e($currentSort) ?>">
    <?php endif; ?>
    <button class="btn primary">Search</button>
    <?php if ($currentQ !== '' || $currentRole !== '' || $currentStatus !== '' || $currentDate !== '' || ($currentSort !== '' && $currentSort !== 'newest')): ?>
      <a class="btn ghost" href="<?= e(Url::to('/admin/users')) ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <table class="data-table staff-data-table">
      <!-- Sortable and filterable column headers -->
      <thead>
        <tr>
          <th class="th-account">
            <div class="th-header-cell">
              <span class="th-title">Account</span>
              <details class="th-dropdown" id="dd-account">
                <summary class="th-icon-btn <?= str_starts_with($currentSort, 'name_') ? 'is-active' : '' ?>" title="Sort Account" aria-label="Sort Account">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
                  <?php if (str_starts_with($currentSort, 'name_')): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Sort Account</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'name_asc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'name_asc')">
                    <span>Name A – Z</span>
                    <?= $currentSort === 'name_asc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'name_desc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'name_desc')">
                    <span>Name Z – A</span>
                    <?= $currentSort === 'name_desc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (str_starts_with($currentSort, 'name_')): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('sort', '')">Reset Sort</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-avatar">
            <div class="th-header-cell">
              <span class="th-title">Avatar</span>
            </div>
          </th>

          <th class="th-location">
            <div class="th-header-cell">
              <span class="th-title">Location</span>
              <details class="th-dropdown" id="dd-location">
                <summary class="th-icon-btn <?= str_starts_with($currentSort, 'location_') ? 'is-active' : '' ?>" title="Sort Location" aria-label="Sort Location">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
                  <?php if (str_starts_with($currentSort, 'location_')): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Sort Location</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'location_asc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'location_asc')">
                    <span>Location A – Z</span>
                    <?= $currentSort === 'location_asc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'location_desc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'location_desc')">
                    <span>Location Z – A</span>
                    <?= $currentSort === 'location_desc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (str_starts_with($currentSort, 'location_')): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('sort', '')">Reset Sort</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-jokes">
            <div class="th-header-cell">
              <span class="th-title">Jokes</span>
              <details class="th-dropdown" id="dd-jokes">
                <summary class="th-icon-btn <?= str_starts_with($currentSort, 'jokes_') ? 'is-active' : '' ?>" title="Sort Jokes" aria-label="Sort Jokes">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>
                  <?php if (str_starts_with($currentSort, 'jokes_')): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Sort Jokes</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'jokes_desc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'jokes_desc')">
                    <span>Most Jokes</span>
                    <?= $currentSort === 'jokes_desc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'jokes_asc' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'jokes_asc')">
                    <span>Least Jokes</span>
                    <?= $currentSort === 'jokes_asc' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (str_starts_with($currentSort, 'jokes_')): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('sort', '')">Reset Sort</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-email">
            <div class="th-header-cell">
              <span class="th-title">Email</span>
            </div>
          </th>

          <th class="th-role">
            <div class="th-header-cell">
              <span class="th-title">Role</span>
              <details class="th-dropdown" id="dd-role">
                <summary class="th-icon-btn <?= !empty($currentRole) ? 'is-active' : '' ?>" title="Filter Role" aria-label="Filter Role">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                  <?php if (!empty($currentRole)): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Filter by Role</div>
                  <button type="button" class="th-menu-item <?= $currentRole === '' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_role', '')">
                    <span>All Roles</span>
                    <?= $currentRole === '' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentRole === 'client' || $currentRole === 'user' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_role', 'client')">
                    <span>Client</span>
                    <?= $currentRole === 'client' || $currentRole === 'user' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentRole === 'moderator' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_role', 'moderator')">
                    <span>Moderator</span>
                    <?= $currentRole === 'moderator' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentRole === 'admin' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_role', 'admin')">
                    <span>Admin</span>
                    <?= $currentRole === 'admin' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                </div>
              </details>
            </div>
          </th>

          <th class="th-status">
            <div class="th-header-cell">
              <span class="th-title">Status</span>
              <details class="th-dropdown" id="dd-status">
                <summary class="th-icon-btn <?= !empty($currentStatus) || str_starts_with($currentSort, 'status_') ? 'is-active' : '' ?>" title="Filter or Sort Status" aria-label="Filter or Sort Status">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                  <?php if (!empty($currentStatus) || str_starts_with($currentSort, 'status_')): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Filter by Status</div>
                  <button type="button" class="th-menu-item <?= $currentStatus === '' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_status', '')">
                    <span>All Statuses</span>
                    <?= $currentStatus === '' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentStatus === 'active' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_status', 'active')">
                    <span>Active (Unsuspended)</span>
                    <?= $currentStatus === 'active' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentStatus === 'suspended' ? 'is-selected' : '' ?>" onclick="submitColFilter('filter_status', 'suspended')">
                    <span>Suspended (Banned)</span>
                    <?= $currentStatus === 'suspended' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <div class="th-menu-divider"></div>
                  <div class="th-menu-title">Sort by Status</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'status_suspended' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'status_suspended')">
                    <span>Suspended First</span>
                    <?= $currentSort === 'status_suspended' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'status_active' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'status_active')">
                    <span>Active First</span>
                    <?= $currentSort === 'status_active' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (!empty($currentStatus) || str_starts_with($currentSort, 'status_')): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('filter_status', ''); submitColFilter('sort', '');">Reset Status</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-date">
            <div class="th-header-cell">
              <span class="th-title">Date</span>
              <details class="th-dropdown" id="dd-date">
                <summary class="th-icon-btn <?= !empty($currentDate) || in_array($currentSort, ['date_desc', 'date_asc', 'newest', 'oldest'], true) ? 'is-active' : '' ?>" title="Date Options" aria-label="Date Options">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <?php if (!empty($currentDate) || in_array($currentSort, ['date_desc', 'date_asc', 'newest', 'oldest'], true)): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu th-menu-date">
                  <div class="th-menu-title">Filter by Day</div>
                  <div class="th-date-input-wrap">
                    <input type="date" class="th-date-native" value="<?= e($currentDate) ?>" onchange="submitColFilter('filter_date', this.value)" title="Choose date">
                  </div>
                  <div class="th-menu-divider"></div>
                  <div class="th-menu-title">Sort by Date</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'date_desc' || $currentSort === 'newest' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'date_desc')">
                    <span>Newest</span>
                    <?= $currentSort === 'date_desc' || $currentSort === 'newest' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'date_asc' || $currentSort === 'oldest' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'date_asc')">
                    <span>Oldest</span>
                    <?= $currentSort === 'date_asc' || $currentSort === 'oldest' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (!empty($currentDate) || in_array($currentSort, ['date_desc', 'date_asc', 'newest', 'oldest'], true)): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('filter_date', ''); submitColFilter('sort', '');">Reset Date</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-time">
            <div class="th-header-cell">
              <span class="th-title">Time</span>
              <details class="th-dropdown" id="dd-time">
                <summary class="th-icon-btn <?= in_array($currentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true) ? 'is-active' : '' ?>" title="Sort Time" aria-label="Sort Time">
                  <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?php if (in_array($currentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true)): ?><span class="th-active-dot"></span><?php endif; ?>
                </summary>
                <div class="th-menu">
                  <div class="th-menu-title">Sort Time</div>
                  <button type="button" class="th-menu-item <?= $currentSort === 'time_asc' || $currentSort === 'earliest' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'time_asc')">
                    <span>Earliest</span>
                    <?= $currentSort === 'time_asc' || $currentSort === 'earliest' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <button type="button" class="th-menu-item <?= $currentSort === 'time_desc' || $currentSort === 'latest' ? 'is-selected' : '' ?>" onclick="submitColFilter('sort', 'time_desc')">
                    <span>Latest</span>
                    <?= $currentSort === 'time_desc' || $currentSort === 'latest' ? '<span class="th-check">✓</span>' : '' ?>
                  </button>
                  <?php if (in_array($currentSort, ['time_asc', 'time_desc', 'earliest', 'latest'], true)): ?>
                    <button type="button" class="th-menu-item th-menu-clear" onclick="submitColFilter('sort', '')">Reset Time</button>
                  <?php endif; ?>
                </div>
              </details>
            </div>
          </th>

          <th class="th-actions">
            <div class="th-header-cell th-actions-cell">
              <span class="th-title">Actions</span>
            </div>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): // Show empty state row when no accounts match filters. ?>
          <tr>
            <td colspan="10" class="staff-empty-row" style="text-align:center;padding:28px;color:var(--muted)">
              No accounts found.
            </td>
          </tr>
        <?php endif; // End empty users check. ?>

        <?php
        $perPage = 5;
        $totalUsers = count($users);
        $pageCount = max(1, (int) ceil($totalUsers / $perPage));
        $page = max(1, min($pageCount, (int) ($_GET['page'] ?? 1)));
        $pagedUsers = array_slice($users, ($page - 1) * $perPage, $perPage);
        foreach ($pagedUsers as $u): // Render one table row per user account on current page.
          $id = (int) $u['id']; // Cast user ID to integer.
          $rawRoles = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $u['roles']))))); // Parse comma-separated roles into unique trimmed slugs.
          $staff = (bool) array_intersect($rawRoles, ['admin', 'moderator']); // Flag whether this user holds staff privileges.
          $displayRoles = array_values(array_unique(array_map(function ($r) { // Map internal role slugs to UI labels.
            $t = strtolower(trim($r)); // Normalize role slug to lowercase.
            return match ($t) { // Translate internal slug to display label.
              'user' => 'Client',
              'moderator' => 'Moderator',
              'admin' => 'Admin',
              default => ucfirst($r),
            };
          }, $rawRoles)));
          $displayRoleText = implode(', ', $displayRoles) ?: 'Client'; // Join display labels with a Client fallback.
          $roleKey = in_array('admin', $rawRoles, true) ? 'admin' : (in_array('moderator', $rawRoles, true) ? 'moderator' : 'client'); // Pick primary badge style key.
          $createdAt = !empty($u['created_at']) ? strtotime((string) $u['created_at']) : null; // Parse account creation timestamp.
          $displayDate = $createdAt ? date('Y-m-d', $createdAt) : '—'; // Format creation date or show placeholder.
          $displayTime = $createdAt ? date('H:i:s', $createdAt) : '—'; // Format creation time or show placeholder.
          $jokeCount = (int) ($u['joke_count'] ?? 0); // Cast authored joke count to integer.
        ?>
          <tr>
            <td>
              <strong><?= e($u['username']) ?></strong>
              <small class="staff-subline">Account #<?= $id ?></small>
            </td>
            <td class="td-avatar">
              <div class="staff-user-avatar" title="<?= e($u['username']) ?>'s avatar">
                <?php if (preg_match('/^preset:(1[0-2]|[1-9])$/', (string) ($u['avatar_path'] ?? ''), $presetMatch)): // Render CSS preset avatar. ?>
                  <span class="preset-avatar preset-<?= (int) $presetMatch[1] ?>" aria-hidden="true"></span>
                <?php elseif (!empty($u['has_avatar'])): // Render custom uploaded avatar image. ?>
                  <img src="<?= e(Url::base() . '/avatar.php?id=' . (int) $u['id']) ?>" alt="<?= e($u['username']) ?>" class="avatar-img">
                <?php else: // Fallback to uppercase first letter of username. ?>
                  <span class="avatar-fallback"><?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?></span>
                <?php endif; // End avatar display branch. ?>
              </div>
            </td>
            <td>
              <span class="staff-location">📍 <?= e($u['location'] ?? 'United Kingdom') ?></span>
            </td>
            <td class="td-jokes">
              <span class="staff-joke-badge" title="<?= $jokeCount ?> jokes"><?= $jokeCount ?></span>
            </td>
            <td><?= e($u['email']) ?></td>
            <td>
              <span class="staff-role staff-role-<?= e($roleKey) ?>"><?= e($displayRoleText) ?></span>
            </td>
            <td>
              <span class="staff-status <?= $u['status'] === 'active' ? 'is-active' : 'is-muted' ?>">
                <?= e(ucfirst((string) $u['status'])) ?>
              </span>
            </td>
            <td>
              <span class="staff-date" <?= $createdAt ? 'data-local-dt="' . e(date('Y-m-d\TH:i:s+07:00', $createdAt)) . '" data-local-fmt="date"' : '' ?>><?= e($displayDate) ?></span>
            </td>
            <td>
              <span class="staff-time" <?= $createdAt ? 'data-local-dt="' . e(date('Y-m-d\TH:i:s+07:00', $createdAt)) . '" data-local-fmt="time"' : '' ?>><?= e($displayTime) ?></span>
            </td>
            <td>
              <div class="staff-row-actions">
                <?php if ($staff && Auth::can('users.manage')): // Allow editing staff accounts. ?>
                  <button type="button" class="btn ghost staff-action" data-open-dialog="staff-edit-<?= $id ?>">Edit</button>
                <?php endif; // End staff edit button check. ?>
                <?php if (Auth::can('users.manage') && $id !== (int) Auth::user()['id'] && !in_array('admin', $rawRoles, true)): // Allow suspending/restoring non-admin accounts other than self. ?>
                  <button type="button" class="btn ghost staff-action <?= $u['status'] === 'active' ? 'staff-danger' : '' ?>" data-open-dialog="member-status-<?= $id ?>">
                    <?= $u['status'] === 'active' ? 'Suspend' : 'Restore' ?>
                  </button>
                <?php endif; // End suspend/restore button check. ?>
                <?php if (in_array('moderator', $rawRoles, true) && Auth::can('roles.manage')): // Allow configuring moderator permissions. ?>
                  <button type="button" class="btn ghost staff-action" data-open-dialog="staff-permissions-<?= $id ?>">Permissions</button>
                <?php endif; // End permissions button check. ?>
                <?php if (Auth::can('users.delete') && $id !== (int) Auth::user()['id']): // Allow deleting any account except the current admin. ?>
                  <button type="button" class="btn ghost staff-action staff-danger" data-open-dialog="staff-delete-<?= $id ?>">Delete</button>
                <?php endif; // End delete button check. ?>
              </div>
            </td>
          </tr>
        <?php endforeach; // End user table rows loop. ?>
      </tbody>
    </table>
  </div>

  <?php if ($pageCount > 1):
    $userPageUrl = static function (int $p): string {
      $qs = $_GET;
      $qs['page'] = $p;
      return Url::to('/admin/users') . '?' . http_build_query($qs);
    };
  ?>
    <nav class="pagination" aria-label="User pages" style="margin-top:16px;">
      <?php if ($page > 1): ?>
        <a class="btn ghost" href="<?= e($userPageUrl($page - 1)) ?>">← Previous</a>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $pageCount; $p++): ?>
        <a class="btn <?= $p === $page ? 'primary' : 'ghost' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> href="<?= e($userPageUrl($p)) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $pageCount): ?>
        <a class="btn ghost" href="<?= e($userPageUrl($page + 1)) ?>">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>

<!-- Dialog for creating a new staff account -->
<?php if (Auth::can('users.manage')): // Render staff creation modal for authorized managers. ?>
  <dialog id="staff-create" class="staff-dialog">
    <form method="post" action="<?= e(Url::to('/admin/staff/create')) ?>" class="stacked-form">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <div class="staff-dialog-heading">
        <div>
          <span class="eyebrow">STAFF ACCOUNTS</span>
          <h2>Create Staff</h2>
        </div>
        <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
      </div>
      <label>
        Username
        <input name="username" required minlength="3" maxlength="40" pattern="[A-Za-z0-9_]+">
      </label>
      <label>
        Email
        <input type="email" name="email" required>
      </label>
      <label>
        Password (12+ characters)
        <input type="password" name="password" minlength="12" required autocomplete="new-password">
      </label>
      <label>
        Role
        <select name="role">
          <option value="moderator">Moderator</option>
          <option value="admin">Admin</option>
        </select>
      </label>
      <div class="staff-dialog-actions">
        <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
        <button class="btn primary">Create Staff</button>
      </div>
    </form>
  </dialog>
<?php endif; ?>

<!-- Per-user action dialogs (edit staff, suspend/restore, moderator permissions, delete) -->
<?php foreach ($users as $u): // Generate per-user action modals.
  $id = (int) $u['id']; // Cast user ID to integer.
  $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $u['roles']))))); // Parse assigned role slugs.
  $staff = (bool) array_intersect($roles, ['admin', 'moderator']); // Flag whether account is staff.
?>
  <?php if ($staff && Auth::can('users.manage')): // Render staff edit modal for staff accounts. ?>
    <dialog id="staff-edit-<?= $id ?>" class="staff-dialog">
      <form class="stacked-form" method="post" action="<?= e(Url::to('/admin/staff/' . $id . '/update')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="staff-dialog-heading">
          <div>
            <span class="eyebrow">STAFF ACCOUNTS</span>
            <h2>Edit <?= e($u['username']) ?></h2>
          </div>
          <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
        </div>
        <label>
          Username
          <input name="username" value="<?= e($u['username']) ?>" required>
        </label>
        <label>
          Email
          <input name="email" type="email" value="<?= e($u['email']) ?>" required>
        </label>
        <label>
          Role
          <select name="role">
            <option value="admin" <?= in_array('admin', $roles, true) ? 'selected' : '' ?>>Admin</option>
            <option value="moderator" <?= in_array('moderator', $roles, true) ? 'selected' : '' ?>>Moderator</option>
          </select>
        </label>
        <label>
          Status
          <select name="status">
            <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $u['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          </select>
        </label>
        <label>
          New password (leave blank to keep)
          <input type="password" name="password" minlength="12" autocomplete="new-password">
        </label>
        <div class="staff-dialog-actions">
          <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
          <button class="btn primary">Save Changes</button>
        </div>
      </form>
    </dialog>
  <?php endif; ?>

  <?php if (Auth::can('users.manage') && $id !== (int) Auth::user()['id'] && !in_array('admin', $roles, true)): // Render suspend/restore confirmation modal for non-admin accounts. ?>
    <dialog id="member-status-<?= $id ?>" class="staff-dialog staff-dialog-sm">
      <form class="stacked-form" method="post" action="<?= e(Url::to('/admin/users/' . $id)) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
        <div class="staff-dialog-heading">
          <h2><?= $u['status'] === 'active' ? 'Suspend' : 'Restore' ?> <?= in_array('moderator', $roles, true) ? 'moderator' : 'member' ?>?</h2>
          <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
        </div>
        <p>
          <?= $u['status'] === 'active'
            ? 'Suspended accounts are signed out at their next request and cannot sign in.'
            : 'This restores sign-in access for this account.' ?>
        </p>
        <p><strong><?= e($u['username']) ?></strong></p>
        <div class="staff-dialog-actions">
          <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
          <button class="btn <?= $u['status'] === 'active' ? 'ghost staff-danger' : 'primary' ?>">
            <?= $u['status'] === 'active' ? 'Suspend account' : 'Restore account' ?>
          </button>
        </div>
      </form>
    </dialog>
  <?php endif; ?>

  <?php if (in_array('moderator', $roles, true) && Auth::can('roles.manage')): // Render permission dialog for moderators.
    $values = $staffPermissions[$id] ?? []; // Load saved permission overrides for this moderator.
  ?>
    <dialog id="staff-permissions-<?= $id ?>" class="staff-dialog">
      <form class="stacked-form" method="post" action="<?= e(Url::to('/admin/staff/' . $id . '/permissions')) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="staff-dialog-heading">
          <div>
            <span class="eyebrow">ACCESS CONTROL</span>
            <h2><?= e($u['username']) ?> Permissions</h2>
          </div>
          <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
        </div>
        <div class="staff-permission-list">
          <?php foreach ($permissionKeys as $key): // Render one toggle checkbox per permission key.
            $default = in_array($key, ['analytics.view', 'categories.manage', 'jokes.manage', 'reports.manage', 'moderation.view', 'moderation.review'], true); // Default enabled permissions for moderators.
            $checked = array_key_exists($key, $values) ? (bool) $values[$key] : $default; // Use saved override if present, otherwise default.
          ?>
            <label class="staff-checkbox-row">
              <span><strong><?= e($key) ?></strong></span>
              <input type="checkbox" name="permissions[<?= e($key) ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="staff-dialog-actions">
          <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
          <button class="btn primary">Save Permissions</button>
        </div>
      </form>
    </dialog>
  <?php endif; ?>

  <?php if (Auth::can('users.delete') && $id !== (int) Auth::user()['id']): // Render permanent deletion modal for accounts other than self. ?>
    <dialog id="staff-delete-<?= $id ?>" class="staff-dialog staff-dialog-sm">
      <form method="post" action="<?= e(Url::to('/admin/users/' . $id . '/delete')) ?>" class="stacked-form">
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
        <div class="staff-dialog-heading">
          <h2>Delete Permanently?</h2>
          <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
        </div>
        <p>
          Deleting <strong><?= e($u['username']) ?></strong> permanently removes their account, jokes, comments and associated records. This cannot be undone.
        </p>
        <label>
          Type <strong><?= e($u['username']) ?></strong> to confirm
          <input name="confirm_username" required placeholder="<?= e($u['username']) ?>" autocomplete="off" data-confirm-exact="<?= e($u['username']) ?>">
        </label>
        <div class="staff-dialog-actions">
          <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
          <button class="btn ghost staff-danger">Delete Permanently</button>
        </div>
      </form>
    </dialog>
  <?php endif; // End permanent deletion modal. ?>
<?php endforeach; // End per-user action modal loop. ?>

<script>
// Update a single query parameter for column sorting/filtering and reload.
function submitColFilter(key, val) {
  var url = new URL(window.location.href);
  if (val !== undefined && val !== null && String(val).trim() !== '') {
    url.searchParams.set(key, String(val).trim());
  } else {
    url.searchParams.delete(key);
  }
  window.location.href = url.toString();
}

// Close any open column dropdown when clicking outside of it.
document.addEventListener('click', function (e) {
  document.querySelectorAll('details.th-dropdown[open]').forEach(function (detail) {
    if (!detail.contains(e.target)) {
      detail.removeAttribute('open');
    }
  });
});
</script>
