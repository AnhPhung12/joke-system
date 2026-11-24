<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Session, Url, View};
use App\Models\Admin;
use PDOException;

/**
 * AdminController — RBAC-Protected Controller for Multi-Table Admin CRUD.
 *
 * Purpose: Handles HTTP requests for the admin panel: overview analytics,
 *          user/moderator management, permission overrides, categories, jokes,
 *          reports, and system settings (including Gemini API connectivity test).
 * Connections: Coordinates `App\Models\{Admin, Moderation}` with `admin/*` views
 *              and `App\Core\{Auth, Csrf, Session, Url, View}`.
 * Security: Every action enforces granular permissions (`users.manage`,
 *           `categories.manage`, etc.) and `Csrf::enforce()` before mutating state.
 */
final class AdminController
{
    /**
     * Shared guard ensuring the current user holds basic admin panel access.
     */
    private function guard(): void
    {
        // Require basic admin panel access permission
        Auth::requirePermission('admin.view');
    }

    /**
     * Render the admin overview dashboard with platform stats and 30-day growth.
     */
    public function overview(): void
    {
        // Require analytics view permission
        Auth::requirePermission('analytics.view');
        // Instantiate Admin model and render overview metrics
        $a = new Admin();
        View::render('admin/overview', [
            'title' => 'Admin · Overview',
            'stats' => $a->stats(),
            'growth' => $a->growth(),
        ]);
    }

    /**
     * Render the searchable, filterable user and staff directory.
     */
    public function users(): void
    {
        // Require user directory view permission
        Auth::requirePermission('users.view');
        $a = new Admin();

        // Read query filters from GET parameters
        $q = trim((string) ($_GET['q'] ?? ''));
        $role = trim((string) ($_GET['filter_role'] ?? $_GET['role'] ?? ''));
        $status = trim((string) ($_GET['filter_status'] ?? $_GET['status'] ?? ''));
        $sort = trim((string) ($_GET['sort'] ?? 'newest'));
        $date = trim((string) ($_GET['filter_date'] ?? $_GET['date'] ?? ''));

        // Fetch filtered and sorted user records
        $users = $a->users($q, $role, $sort, $date, $status);

        // Load per-moderator permission overrides for display in the staff matrix
        $perms = [];
        foreach ($users as $u) {
            if (str_contains((string) $u['roles'], 'moderator')) {
                $perms[(int) $u['id']] = $a->staffPermissions((int) $u['id']);
            }
        }

        // Render the admin users management view
        View::render('admin/users', [
            'title' => 'Admin · Users',
            'users' => $users,
            'q' => $q,
            'role' => $role,
            'filter_role' => $role,
            'filter_status' => $status,
            'sort' => $sort,
            'filter_date' => $date,
            'staffPermissions' => $perms,
            'permissionKeys' => Admin::STAFF_PERMISSIONS,
        ]);
    }

    /**
     * Update a user's role and active/suspended status.
     */
    public function saveUser(int $id): void
    {
        // Enforce user management permission and CSRF token
        Auth::requirePermission('users.manage');
        Csrf::enforce();

        // Apply role and status update via Admin model
        $ok = (new Admin())->setUser(
            $id,
            (int) Auth::user()['id'],
            (string) ($_POST['role'] ?? ''),
            (string) ($_POST['status'] ?? '')
        );

        // Flash outcome message and redirect to user directory
        Session::flash(
            $ok
                ? 'Account updated.'
                : 'Unable to update the account: check its role or the last administrator.'
        );
        Url::redirect('/admin/users');
    }

    /**
     * Display the category management page.
     */
    public function categories(): void
    {
        // Enforce category management permission
        Auth::requirePermission('categories.manage');
        // Render category list view
        View::render('admin/categories', [
            'title' => 'Admin · Categories',
            'categories' => (new Admin())->categories(),
        ]);
    }

    /**
     * Validate and save (create or update) a joke category.
     */
    public function saveCategory(): void
    {
        // Enforce category management permission and CSRF token
        Auth::requirePermission('categories.manage');
        Csrf::enforce();

        // Read and normalize submitted category fields
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $desc = trim((string) ($_POST['description'] ?? ''));

        // Enforce field lengths and URL-safe slug format
        if (
            mb_strlen($name) < 2
            || mb_strlen($name) > 80
            || !preg_match('/^[a-z0-9-]{2,100}$/', $slug)
            || mb_strlen($desc) > 500
        ) {
            http_response_code(422);
            exit('Invalid category data');
        }

        try {
            // Insert or update category record
            (new Admin())->saveCategory($id ?: null, $name, $slug, $desc, isset($_POST['is_active']));
            Session::flash('Category saved.');
        } catch (PDOException $e) {
            // Handle duplicate name or slug constraint violation
            Session::flash('Unable to save: name or slug already exists.');
        }

        // Redirect back to categories page
        Url::redirect('/admin/categories');
    }

    /**
     * Display the system settings page.
     */
    public function settings(): void
    {
        // Enforce settings management permission
        Auth::requirePermission('settings.manage');
        // Render system settings view
        View::render('admin/settings', [
            'title' => 'Admin · Settings',
            'settings' => (new Admin())->settings(),
        ]);
    }

    /**
     * Save public/gameplay settings or trigger the Gemini API connectivity test.
     */
    public function saveSettings(): void
    {
        // Enforce settings management permission and CSRF token
        Auth::requirePermission('settings.manage');
        Csrf::enforce();

        // Dispatch AI ping test if requested via form action button
        if (isset($_POST['action']) && $_POST['action'] === 'test_ai') {
            $this->testAi();
            return;
        }

        // Whitelist of editable settings and their maximum character lengths
        $allowed = [
            'site_name' => 80,
            'site_slogan' => 160,
            'joke_battle_rounds' => 2,
        ];
        $a = new Admin();

        // Validate and persist each submitted setting in the whitelist
        foreach ($allowed as $key => $limit) {
            if (!array_key_exists($key, $_POST)) {
                continue;
            }

            $value = trim((string) $_POST[$key]);

            // Reject empty or over-length setting values
            if ($value === '' || mb_strlen($value) > $limit) {
                http_response_code(422);
                exit('Invalid setting');
            }

            // Enforce 1-10 integer range for Joke Battle round count
            if ($key === 'joke_battle_rounds' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 10)) {
                http_response_code(422);
                exit('Rounds must be 1–10');
            }

            // Save setting key-value pair with acting admin ID
            $a->setSetting($key, $value, (int) Auth::user()['id']);
        }

        // Flash confirmation and redirect back to settings
        Session::flash('Public settings saved.');
        Url::redirect('/admin/settings');
    }

    /**
     * Send a lightweight ping request to the configured Google Gemini endpoint.
     */
    private function testAi(): void
    {
        // Verify Gemini API key is present in server environment
        $key = getenv('JOKE_AI_API_KEY');
        if (!$key) {
            Session::flash('AI API Key is missing. Please set JOKE_AI_API_KEY in your server environment.');
            Url::redirect('/admin/settings');
        }

        // Ensure PHP cURL extension is available
        if (!function_exists('curl_init')) {
            Session::flash('PHP cURL extension is not enabled on this server.');
            Url::redirect('/admin/settings');
        }

        // Resolve target Gemini model and fallback list
        $model = trim((string) (getenv('JOKE_AI_MODEL') ?: 'gemini-3-flash-lite'));
        $model = preg_replace('~^models/~', '', $model);
        $fallbackModels = array_values(array_unique([
            $model,
            'gemini-3-flash-lite',
            'gemini-3.5-flash-lite',
            'gemini-3.8-flash',
        ]));

        // Build minimal ping payload with 10-token cap
        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => 'Ping test. Respond with OK.']],
                ],
            ],
            'generationConfig' => ['maxOutputTokens' => 10],
        ];

        $status = 0;
        $err = '';
        $usedModel = $model;

        foreach ($fallbackModels as $candidateModel) {
            $usedModel = $candidateModel;
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $candidateModel . ':generateContent';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 15,
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($status >= 200 && $status < 300) {
                break;
            }
            if (!in_array($status, [0, 404, 429, 502, 503, 504], true)) {
                break;
            }
        }

        // Flash connectivity test result to the admin settings page
        if ($status >= 200 && $status < 300) {
            Session::flash('✓ AI API Connected! Google Gemini responded with HTTP ' . $status . ' OK (Model: ' . $usedModel . ').');
        } else {
            Session::flash('AI API test failed with HTTP ' . $status . ' (Model: ' . $usedModel . ')' . ($err ? ': ' . $err : ''));
        }

        Url::redirect('/admin/settings');
    }

    /**
     * Create a new moderator account from the admin users panel.
     */
    public function createStaff(): void
    {
        // Enforce user management permission and CSRF token
        Auth::requirePermission('users.manage');
        Csrf::enforce();

        try {
            // Create moderator account via Admin model
            (new Admin())->createStaff(
                trim((string) ($_POST['username'] ?? '')),
                trim((string) ($_POST['email'] ?? '')),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['role'] ?? '')
            );
            Session::flash('Staff account created.');
        } catch (\Throwable $e) {
            // Surface validation or duplicate-account error
            Session::flash('Could not create staff: ' . $e->getMessage());
        }

        Url::redirect('/admin/users');
    }

    /**
     * Update an existing moderator account's details.
     */
    public function updateStaff(int $id): void
    {
        // Enforce user management permission and CSRF token
        Auth::requirePermission('users.manage');
        Csrf::enforce();

        try {
            // Persist moderator profile, status, and optional password update
            (new Admin())->updateStaff(
                $id,
                trim((string) ($_POST['username'] ?? '')),
                trim((string) ($_POST['email'] ?? '')),
                (string) ($_POST['role'] ?? ''),
                (string) ($_POST['status'] ?? ''),
                (string) ($_POST['password'] ?? ''),
                (int) Auth::user()['id']
            );
            Session::flash('Staff updated.');
        } catch (\Throwable $e) {
            Session::flash('Update failed: ' . $e->getMessage());
        }

        Url::redirect('/admin/users');
    }

    /**
     * Permanently delete a user account after username confirmation.
     */
    public function deleteUser(int $id): void
    {
        // Enforce user deletion permission and CSRF token
        Auth::requirePermission('users.delete');
        Csrf::enforce();

        try {
            // Execute hard delete after verifying exact username confirmation
            (new Admin())->hardDelete(
                $id,
                (int) Auth::user()['id'],
                trim((string) ($_POST['confirm_username'] ?? ''))
            );
            Session::flash('Account and authored content permanently deleted.');
        } catch (\Throwable $e) {
            Session::flash('Deletion failed: ' . $e->getMessage());
        }

        Url::redirect('/admin/users');
    }

    /**
     * Save granular permission overrides for a moderator account.
     */
    public function savePermissions(int $id): void
    {
        // Enforce role management permission and CSRF token
        Auth::requirePermission('roles.manage');
        Csrf::enforce();

        try {
            // Persist per-moderator permission overrides
            (new Admin())->savePermissions($id, (array) ($_POST['permissions'] ?? []));
            Session::flash('Moderator permissions updated.');
        } catch (\Throwable $e) {
            Session::flash('Permissions not saved: ' . $e->getMessage());
        }

        Url::redirect('/admin/users');
    }

    /**
     * Delete an empty category.
     */
    public function deleteCategory(int $id): void
    {
        // Enforce category management permission and CSRF token
        Auth::requirePermission('categories.manage');
        Csrf::enforce();

        // Delete category if unused and flash result
        Session::flash(
            (new Admin())->deleteCategory($id)
                ? 'Category deleted.'
                : 'Category contains jokes and cannot be deleted.'
        );
        Url::redirect('/admin/categories');
    }

    /**
     * Display the admin joke management table.
     */
    public function jokes(): void
    {
        // Enforce joke management permission
        Auth::requirePermission('jokes.manage');
        // Render admin jokes table
        View::render('admin/manage-jokes', [
            'title' => 'Manage Jokes',
            'jokes' => (new Admin())->jokes(),
        ]);
    }

    /**
     * Permanently delete a joke from the admin jokes table.
     */
    public function deleteJoke(int $id): void
    {
        // Enforce joke management permission and CSRF token
        Auth::requirePermission('jokes.manage');
        Csrf::enforce();

        // Hard-delete joke and associated reports/logs
        (new Admin())->deleteJoke($id);
        Session::flash('Joke permanently deleted.');
        Url::redirect('/admin/jokes');
    }

    /**
     * Display the combined moderation queue, user reports, and comments list.
     */
    public function reports(): void
    {
        // Enforce report management permission
        Auth::requirePermission('reports.manage');

        // Read optional comment sort and date filter parameters
        $sort = (string) ($_GET['comment_sort'] ?? 'date_desc');
        $date = (string) ($_GET['comment_date'] ?? '');
        $m = new \App\Models\Moderation();

        // Render moderation queue, reports, and comments view
        View::render('admin/reports', [
            'title' => 'Reports',
            'jokes' => $m->queue(),
            'reports' => $m->reports(),
            'comments' => $m->comments($sort, $date),
            'commentSort' => $sort,
            'commentDate' => $date,
        ]);
    }
}
