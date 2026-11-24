<?php
/**
 * Joke Create / Edit Form Template
 *
 * Shared form for submitting a new joke (`POST /jokes/create`) or editing an
 * existing joke (`POST /jokes/{id}/edit`). Initializes TinyMCE on
 * `#joke-content-editor` with a restricted safe HTML element whitelist.
 * Includes CSRF protection and escapes form values with `e()`.
 *
 * @var array|null $joke
 * @var array $categories
 */

use App\Core\{Csrf, Url}; // Import CSRF and URL helpers

$editing = (bool)$joke; // Determine whether form is editing an existing joke or creating a new one
$action = $editing ? '/jokes/' . $joke['id'] . '/edit' : '/jokes/create'; // Resolve target POST route
?>
<!-- Joke create/edit heading -->
<div class="page-heading">
    <span class="eyebrow">YOUR CREATIVE SPACE</span>
    <h1>
        <?= $editing ? 'Edit Joke' : 'Create a Joke' ?>
        <span class="chibi chibi-silly chibi-inline" aria-hidden="true"></span>
    </h1>
    <p>Your joke will be reviewed by a moderator before publication.</p>
</div>

<!-- Joke submission form with CSRF protection -->
<section class="panel form-panel">
    <form class="stacked-form" method="post" action="<?= e(Url::to($action)) ?>" id="jokeSubmissionForm">
        <!-- Hidden CSRF token field -->
        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

        <label>
            Title
            <input
                name="title"
                required
                minlength="3"
                maxlength="180"
                value="<?= e($joke['title'] ?? '') ?>"
                placeholder="A funny title..."
            >
        </label>

        <label for="joke-content-editor">
            Content <small>Formatting is allowed; unsafe HTML is removed before saving.</small>
        </label>
        <!-- Rich-text textarea enhanced by TinyMCE -->
        <textarea
            id="joke-content-editor"
            name="content"
            rows="8"
            placeholder="Tell your joke..."
        ><?= e($joke['content'] ?? '') ?></textarea>

        <label>
            Category
            <select name="category_id" required>
                <option value="">Choose category</option>
                <?php foreach ($categories as $cat): // Populate category options and preselect current category ?>
                    <option
                        value="<?= (int)$cat['id'] ?>"
                        <?= (int)($joke['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>
                    >
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; // End category options loop ?>
            </select>
        </label>

        <div class="inline-actions">
            <button type="submit" class="btn primary"><?= $editing ? 'Save & resubmit' : 'Submit for review' ?></button>
            <a class="btn ghost" href="<?= e(Url::to('/my-jokes')) ?>">Cancel</a>
        </div>
    </form>
</section>

<!-- TinyMCE rich-text editor initialization -->
<script src="<?= e(Url::to('/assets/vendor/tinymce/tinymce.min.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Guard against missing TinyMCE script and initialize with safe element whitelist
    if (!window.tinymce) return;
    tinymce.init({
        selector: '#joke-content-editor',
        base_url: '<?= e(Url::to('/assets/vendor/tinymce')) ?>',
        suffix: '.min',
        license_key: 'gpl',
        menubar: false,
        height: 320,
        plugins: 'lists link emoticons',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link emoticons | removeformat',
        valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,a[href|target|rel]',
        branding: false,
        promotion: false,
        setup: function (editor) {
            editor.on('change input keyup', function () { editor.save(); });
        }
    });
    var form = document.getElementById('jokeSubmissionForm');
    if (form) {
        form.addEventListener('submit', function () {
            if (window.tinymce) tinymce.triggerSave();
        });
    }
});
</script>
