<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\Comment;
use App\Models\Joke;

/**
 * JokeController — MVC controller for the Joke Library, CRUD operations, and ownership enforcement.
 *
 * Purpose:
 *   Handles browsing, searching, viewing, creating, editing, deleting, and liking/saving jokes.
 * Connections:
 *   - Uses App\Models\Joke and App\Models\Comment for persistence and queries.
 *   - Renders templates under templates/jokes/* and templates/errors/* via View::render().
 * OOP:
 *   - Final controller class encapsulating a Joke model instance.
 * Data Structures & Algorithms:
 *   - Pagination: Clamps requested page into [1, ceil(total / 12)] before querying LIMIT/OFFSET.
 *   - Ownership Control: edit(), update(), and destroy() verify joke.user_id === Auth::user()['id'], returning 403 otherwise.
 *   - Sanitization: Strips unsafe HTML from TinyMCE input via sanitize_joke_html().
 */
final class JokeController
{
    // Joke model instance for database queries and persistence.
    private Joke $model;

    public function __construct()
    {
        // Instantiate the Joke model for this controller.
        $this->model = new Joke();
    }

    /**
     * Display the paginated, searchable, and sortable joke library.
     */
    public function index(): void
    {
        // Read and normalize search query, category filter, sort order, and page number.
        $q = trim((string) ($_GET['q'] ?? ''));
        $category = max(0, (int) ($_GET['category'] ?? 0));
        $sort = (string) ($_GET['sort'] ?? 'newest');
        $requestedPage = max(1, min(1000, (int) ($_GET['page'] ?? 1)));

        // Count matching jokes and clamp the requested page within valid bounds.
        $total = $this->model->countSearch($q, $category);
        $pageCount = max(1, (int) ceil($total / 12));
        $page = min($requestedPage, $pageCount);

        // Render the joke library index view with paginated results and filter state.
        View::render('jokes/index', [
            'title' => 'Joke Library',
            'jokes' => $this->model->search($q, $category, $sort, $page),
            'categories' => $this->model->categories(),
            'q' => $q,
            'category' => $category,
            'sort' => $sort,
            'page' => $page,
            'pageCount' => $pageCount,
            'total' => $total,
        ]);
    }

    /**
     * Redirect to a random published joke, or back to the library if none exist.
     */
    public function surprise(): void
    {
        // Fetch a random published joke ID from the model.
        $id = $this->model->randomPublishedId();
        // Flash a notice and return to the library if no published jokes exist.
        if ($id === null) {
            Session::flash('No published jokes yet.');
            Url::redirect('/jokes');
        }

        // Redirect to the selected joke detail page.
        Url::redirect('/jokes/' . $id);
    }

    /**
     * Show a single joke with its comments and user reaction state.
     * Non-published jokes are only visible to their author or moderators.
     */
    public function show(int $id): void
    {
        // Load the joke record and the currently authenticated user (if any).
        $joke = $this->model->find($id);
        $user = Auth::user();

        // Restrict unpublished jokes to the author or users with moderation permission.
        $isUnpublished = !$joke
            || (
                $joke['status'] !== 'published'
                && (!$user || ((int) $user['id'] !== (int) $joke['user_id'] && !Auth::can('moderation.review')))
            );

        // Return 404 when the joke is missing or not visible to the current viewer.
        if ($isUnpublished) {
            http_response_code(404);
            View::render('errors/404');
            return;
        }

        // Fetch visible comments and the current user's like/favorite flags.
        $comments = (new Comment())->forJoke($id);
        $liked = $user ? $this->model->isSelected((int) $user['id'], $id, 'likes') : false;
        $saved = $user ? $this->model->isSelected((int) $user['id'], $id, 'favorites') : false;

        // Render the joke detail view with comments and reaction state.
        View::render(
            'jokes/show',
            compact('joke', 'comments', 'liked', 'saved') + ['title' => $joke['title']]
        );
    }

    /**
     * Render the joke creation form.
     */
    public function create(): void
    {
        // Require permission to create jokes before showing the form.
        Auth::requirePermission('joke.create');

        // Render the shared joke form in creation mode.
        View::render('jokes/form', [
            'title' => 'Create Joke',
            'categories' => $this->model->categories(),
            'joke' => null,
        ]);
    }

    /**
     * Render the joke edit form after verifying ownership and non-deleted status.
     */
    public function edit(int $id): void
    {
        // Require joke creation permission and load the target joke.
        Auth::requirePermission('joke.create');
        $joke = $this->model->find($id);

        // Enforce ownership and ensure deleted jokes cannot be edited.
        if (!$joke || (int) $joke['user_id'] !== (int) Auth::user()['id'] || $joke['status'] === 'deleted') {
            $this->forbidden();
            return;
        }

        // Render the shared joke form pre-populated with the existing joke.
        View::render('jokes/form', [
            'title' => 'Edit Joke',
            'categories' => $this->model->categories(),
            'joke' => $joke,
        ]);
    }

    /**
     * Validate and sanitize POST form input for creating or updating a joke.
     */
    private function input(): ?array
    {
        // Extract, sanitize, and normalize submitted joke fields.
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = sanitize_joke_html((string) ($_POST['content'] ?? ''));
        $plainContent = joke_plain_text($content);
        $category = (int) ($_POST['category_id'] ?? 0);
        $country = strtoupper(trim((string) ($_POST['country_code'] ?? 'GB')));
        $language = 'en';

        // Ensure the selected category ID exists in the database.
        $validCategory = in_array(
            $category,
            array_map(fn($c) => (int) $c['id'], $this->model->categories()),
            true
        );

        // Validate title/content length bounds, category existence, country code, and language.
        if (
            mb_strlen($title) > 180
            || mb_strlen($title) < 3
            || mb_strlen($plainContent) < 5
            || mb_strlen($plainContent) > 10000
            || !$validCategory
            || !preg_match('/^[A-Z]{2}$/', $country)
            || $language !== 'en'
        ) {
            return null;
        }

        // Return the validated and sanitized payload for persistence.
        return [
            'title' => $title,
            'content' => $content,
            'category_id' => $category,
            'country_code' => $country,
            'language_code' => $language,
        ];
    }

    /**
     * Persist a newly submitted joke and queue it for moderation.
     */
    public function store(): void
    {
        // Enforce permission and CSRF token on POST submission.
        Auth::requirePermission('joke.create');
        Csrf::enforce();

        // Validate input and redirect back to the creation form if invalid.
        $data = $this->input();
        if (!$data) {
            Session::flash('The joke details are invalid.');
            Url::redirect('/jokes/create');
        }

        // Save the new joke, flash confirmation, and redirect to My Jokes.
        $this->model->create((int) Auth::user()['id'], $data);
        Session::flash('Your joke has been submitted for moderator review.');
        Url::redirect('/my-jokes');
    }

    /**
     * Update an existing joke owned by the current user and re-queue it for review.
     */
    public function update(int $id): void
    {
        // Enforce permission and CSRF token on POST submission.
        Auth::requirePermission('joke.create');
        Csrf::enforce();

        // Load the joke and verify ownership and non-deleted status.
        $joke = $this->model->find($id);
        if (!$joke || (int) $joke['user_id'] !== (int) Auth::user()['id'] || $joke['status'] === 'deleted') {
            $this->forbidden();
            return;
        }

        // Validate updated fields and redirect back to the edit form on failure.
        $data = $this->input();
        if (!$data) {
            Session::flash('The joke details are invalid.');
            Url::redirect('/jokes/' . $id . '/edit');
        }

        // Persist the joke update, flash status, and redirect to My Jokes.
        $this->model->update($id, $data);
        Session::flash('Joke updated and returned to the moderation queue.');
        Url::redirect('/my-jokes');
    }

    /**
     * Soft-delete a joke owned by the current user.
     */
    public function destroy(int $id): void
    {
        // Enforce permission and CSRF token on POST submission.
        Auth::requirePermission('joke.create');
        Csrf::enforce();

        // Verify the joke exists and is owned by the current user.
        $joke = $this->model->find($id);
        if (!$joke || (int) $joke['user_id'] !== (int) Auth::user()['id']) {
            $this->forbidden();
            return;
        }

        // Soft-delete the joke, flash confirmation, and redirect to My Jokes.
        $this->model->delete($id);
        Session::flash('Joke deleted.');
        Url::redirect('/my-jokes');
    }

    /**
     * List all jokes authored by the currently signed-in user.
     */
    public function mine(): void
    {
        // Require joke creation permission before viewing personal submissions.
        Auth::requirePermission('joke.create');

        // Render the user's joke list template.
        View::render('jokes/mine', [
            'title' => 'My Jokes',
            'jokes' => $this->model->mine((int) Auth::user()['id']),
        ]);
    }

    /**
     * Toggle a user reaction (like or favorite) on a published joke.
     */
    public function toggle(int $id, string $table): void
    {
        // Require an authenticated user and a valid CSRF token.
        Auth::requireLogin();
        Csrf::enforce();

        // Ensure the target joke exists and is published before toggling reactions.
        $joke = $this->model->find($id);
        if (!$joke || $joke['status'] !== 'published') {
            http_response_code(404);
            exit('Joke not found');
        }

        // Toggle the reaction row in the specified table and redirect back to the joke.
        $this->model->toggle((int) Auth::user()['id'], $id, $table);
        Url::redirect('/jokes/' . $id);
    }

    /**
     * Render a 403 Forbidden error page.
     */
    private function forbidden(): void
    {
        // Send HTTP 403 status and render the forbidden error template.
        http_response_code(403);
        View::render('errors/403', ['title' => 'Forbidden']);
    }
}
