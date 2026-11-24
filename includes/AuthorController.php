<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\User;

/**
 * AuthorController - Displays public profiles for joke authors.
 *
 * Author accounts are stored in the existing `users` table; this controller
 * reuses the User model and does not introduce a duplicate authors table.
 */
final class AuthorController
{
    private User $authors;

    public function __construct()
    {
        $this->authors = new User();
    }

    public function index(): void
    {
        View::render('authors/index', [
            'title' => 'Joke Authors',
            'authors' => $this->authors->authors(),
        ]);
    }
}
