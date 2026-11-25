<?php
/**
 * Admin categories management template.
 * Rendered by the admin categories controller with `$categories` (array of category
 * rows including `id`, `name`, `slug`, `description`, `joke_count`, `is_active`).
 * Provides the categories table and modal dialogs for create, edit, and delete.
 *
 * @var array $categories
 */

use App\Core\{Url, Csrf}; // Import URL and CSRF token helpers.

$perPage = 5;
$totalCats = count($categories);
$pageCount = max(1, (int) ceil($totalCats / $perPage));
$page = max(1, min($pageCount, (int) ($_GET['page'] ?? 1)));
$pagedCategories = array_slice($categories, ($page - 1) * $perPage, $perPage);
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">CONTENT MANAGEMENT</span>
  <h1>Categories</h1>
  <p>Organise your joke library and manage category visibility.</p>
</div>

<!-- Categories data table panel -->
<section class="panel staff-table-panel">
  <div class="staff-panel-heading">
    <div>
      <h2>All Categories</h2>
      <p>Edit category details or manage visibility.</p>
    </div>
    <button type="button" class="btn primary" data-open-dialog="category-create">+ New Category</button>
  </div>

  <div class="table-wrap">
    <table class="data-table staff-data-table">
      <thead>
        <tr>
          <th>Category</th>
          <th>Description</th>
          <th>Jokes</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pagedCategories as $c): // Render one table row per category on current page.
          $id = (int) $c['id']; // Cast category ID to integer.
        ?>
          <tr>
            <td>
              <strong><?= e($c['name']) ?></strong>
              <small class="staff-subline"><?= e($c['slug']) ?></small>
            </td>
            <td class="staff-description"><?= e($c['description']) ?></td>
            <td><?= (int) $c['joke_count'] ?></td>
            <td>
              <span class="staff-status <?= $c['is_active'] ? 'is-active' : 'is-muted' ?>">
                <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <div class="staff-row-actions">
                <button class="btn ghost staff-action" type="button" data-open-dialog="category-edit-<?= $id ?>">Edit</button>
                <button class="btn ghost staff-action staff-danger" type="button" data-open-dialog="category-delete-<?= $id ?>">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pageCount > 1): ?>
    <nav class="pagination" aria-label="Category pages" style="margin-top:16px;">
      <?php if ($page > 1): ?>
        <a class="btn ghost" href="<?= e(Url::to('/admin/categories')) ?>?page=<?= $page - 1 ?>">← Previous</a>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $pageCount; $p++): ?>
        <a class="btn <?= $p === $page ? 'primary' : 'ghost' ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> href="<?= e(Url::to('/admin/categories')) ?>?page=<?= $p ?>"><?= $p ?></a>
      <?php endfor; ?>
      <?php if ($page < $pageCount): ?>
        <a class="btn ghost" href="<?= e(Url::to('/admin/categories')) ?>?page=<?= $page + 1 ?>">Next →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</section>

<!-- Dialog for creating a new category -->
<dialog id="category-create" class="staff-dialog">
  <form method="post" action="<?= e(Url::to('/admin/categories')) ?>" class="stacked-form">
    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
    <div class="staff-dialog-heading">
      <div>
        <span class="eyebrow">NEW CATEGORY</span>
        <h2>Add Category</h2>
      </div>
      <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
    </div>
    <label>
      Name
      <input name="name" required maxlength="80" placeholder="e.g. British Humour">
    </label>
    <label>
      Slug (a-z, 0-9, -)
      <input name="slug" required pattern="[a-z0-9-]{2,100}" placeholder="british-humour">
    </label>
    <label>
      Description
      <textarea name="description" maxlength="500" rows="3"></textarea>
    </label>
    <label class="staff-checkbox-row">
      <span>
        <strong>Active</strong>
        <small>Visible in the Joke Library</small>
      </span>
      <input type="checkbox" name="is_active" value="1" checked>
    </label>
    <div class="staff-dialog-actions">
      <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
      <button class="btn primary">Add Category</button>
    </div>
  </form>
</dialog>

<!-- Per-category Edit and Delete dialogs -->
<?php foreach ($categories as $c): // Generate edit and delete modals for each category.
  $id = (int) $c['id']; // Cast category ID to integer.
?>
  <dialog id="category-edit-<?= $id ?>" class="staff-dialog">
    <form method="post" action="<?= e(Url::to('/admin/categories')) ?>" class="stacked-form">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="staff-dialog-heading">
        <div>
          <span class="eyebrow">EDIT CATEGORY</span>
          <h2><?= e($c['name']) ?></h2>
        </div>
        <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
      </div>
      <label>
        Name
        <input name="name" value="<?= e($c['name']) ?>" required maxlength="80">
      </label>
      <label>
        Slug
        <input name="slug" value="<?= e($c['slug']) ?>" required pattern="[a-z0-9-]{2,100}">
      </label>
      <label>
        Description
        <textarea name="description" maxlength="500" rows="3"><?= e($c['description']) ?></textarea>
      </label>
      <label class="staff-checkbox-row">
        <span>
          <strong>Active</strong>
          <small>Visible in the Joke Library</small>
        </span>
        <input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>>
      </label>
      <div class="staff-dialog-actions">
        <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
        <button class="btn primary">Save Changes</button>
      </div>
    </form>
  </dialog>

  <dialog id="category-delete-<?= $id ?>" class="staff-dialog staff-dialog-sm">
    <form method="post" action="<?= e(Url::to('/admin/categories/' . $id . '/delete')) ?>" class="stacked-form">
      <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
      <div class="staff-dialog-heading">
        <h2>Delete Category?</h2>
        <button type="button" class="staff-dialog-close" data-close-dialog aria-label="Close">×</button>
      </div>
      <p>
        Delete <strong><?= e($c['name']) ?></strong>? Categories containing jokes cannot be deleted. You can deactivate them instead.
      </p>
      <div class="staff-dialog-actions">
        <button type="button" class="btn ghost" data-close-dialog>Cancel</button>
        <button class="btn ghost staff-danger">Delete Category</button>
      </div>
    </form>
  </dialog>
<?php endforeach; ?>
