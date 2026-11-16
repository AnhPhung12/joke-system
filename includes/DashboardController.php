<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Url, View};

/**
 * DashboardController — Role-Aware Home & Dashboard Entry Controller.
 *
 * Purpose: Routes the root `/` landing page (redirecting staff to their highest
 *          permitted admin workspace) and renders role-specific dashboards.
 * Connections: Uses `App\Core\{Auth, Url, View}` and `dashboard/*` templates.
 */
final class DashboardController
{
    /**
     * Render the public home page, or redirect staff to their first permitted admin section.
     */
    public function home(): void
    {
        // Redirect staff members straight to their highest-priority permitted admin section
        if (Auth::hasRole('admin') || Auth::hasRole('moderator')) {
            // Pick the first admin route the current staff member is allowed to access
            $target = Auth::can('analytics.view')
                ? '/admin/overview'
                : (Auth::can('jokes.manage')
                    ? '/admin/jokes'
                    : (Auth::can('reports.manage')
                        ? '/admin/reports'
                        : (Auth::can('users.view')
                            ? '/admin/users'
                            : (Auth::can('categories.manage')
                                ? '/admin/categories'
                                : '/admin/settings'))));

            // Perform HTTP redirect to the resolved admin workspace
            Url::redirect($target);
        }

        // Render the public landing page for guests and standard users
        View::render('dashboard/home', ['title' => 'JOKÉ · Home']);
    }

    /**
     * Render the standard user dashboard.
     */
    public function user(): void
    {
        // Enforce standard user dashboard permission
        Auth::requirePermission('dashboard.view');
        // Render the user dashboard template
        View::render('dashboard/user', ['title' => 'My Dashboard']);
    }

    /**
     * Render the moderator dashboard.
     */
    public function moderator(): void
    {
        // Enforce moderator view permission
        Auth::requirePermission('moderation.view');
        // Render the moderator dashboard template
        View::render('dashboard/moderator', ['title' => 'Moderator Dashboard']);
    }

    /**
     * Render the administrator dashboard.
     */
    public function admin(): void
    {
        // Enforce admin panel view permission
        Auth::requirePermission('admin.view');
        // Render the admin dashboard template
        View::render('dashboard/admin', ['title' => 'Admin Dashboard']);
    }
}
