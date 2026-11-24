<?php

declare(strict_types=1);

use App\Core\{Router, Session};

/**
 * dispatch.php — Central Front-Controller Route Registration & Request Dispatcher.
 *
 * Purpose: Bootstraps the application, starts the PHP session, registers every
 *          GET/POST route on `App\Core\Router`, and dispatches the virtual path
 *          declared by the calling `.php` entrypoint (`$jokeRoute`).
 * Connections: Included by public `.php` entrypoints; instantiates all controllers
 *              in `App\Controllers\*` and routes through `App\Core\Router`.
 */
// Load class autoloader and global helper functions
require_once __DIR__ . '/bootstrap.php';

// Start or resume the PHP session with secure cookie settings
Session::start();

// Instantiate router and core controllers
$router = new Router();
$auth = new App\Controllers\AuthController();
$dash = new App\Controllers\DashboardController();
$jokes = new App\Controllers\JokeController();
$authors = new App\Controllers\AuthorController();
$community = new App\Controllers\CommunityController();
$moderation = new App\Controllers\ModerationController();

// Home, authentication, and role-specific dashboards
$router->get('/', [$dash, 'home']);
$router->get('/login', [$auth, 'loginForm']);
$router->post('/login', [$auth, 'login']);
$router->get('/staff/login', [$auth, 'staffLoginForm']);
$router->post('/staff/login', [$auth, 'staffLogin']);
$router->get('/register', [$auth, 'registerForm']);
$router->post('/register', [$auth, 'register']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/dashboard', [$dash, 'user']);
$router->get('/moderator', [$dash, 'moderator']);
$router->get('/admin', [$dash, 'admin']);

// Password reset OTP flow
$reset = new App\Controllers\PasswordResetController();
$router->get('/forgot-password', [$reset, 'forgotForm']);
$router->post('/forgot-password', [$reset, 'requestOtp']);
$router->get('/reset-password', [$reset, 'resetForm']);
$router->post('/reset-password/verify', [$reset, 'verifyOtp']);
$router->post('/reset-password', [$reset, 'resetPassword']);

// Joke feed, CRUD, likes, and favorites
$router->get('/jokes', [$jokes, 'index']);
$router->get('/authors', [$authors, 'index']);
$router->get('/jokes/surprise', [$jokes, 'surprise']);
$router->get('/jokes/create', [$jokes, 'create']);
$router->post('/jokes/create', [$jokes, 'store']);
$router->get('/my-jokes', [$jokes, 'mine']);
$router->get('/jokes/{id}', [$jokes, 'show']);
$router->get('/jokes/{id}/edit', [$jokes, 'edit']);
$router->post('/jokes/{id}/edit', [$jokes, 'update']);
$router->post('/jokes/{id}/delete', [$jokes, 'destroy']);
$router->post('/jokes/{id}/like', fn(int $id) => $jokes->toggle($id, 'likes'));
$router->post('/jokes/{id}/favorite', fn(int $id) => $jokes->toggle($id, 'favorites'));

// Comments and abuse reporting
$router->post('/jokes/{id}/comments', [$community, 'comment']);
$router->post('/comments/{id}/edit', [$community, 'editComment']);
$router->post('/comments/{id}/delete', [$community, 'deleteComment']);
$router->post('/reports', [$community, 'report']);

// Moderator queue and review actions
$router->get('/moderator/queue', [$moderation, 'queue']);
$router->post('/moderator/jokes/{id}', [$moderation, 'joke']);
$router->post('/moderator/comments/{id}', [$moderation, 'comment']);
$router->post('/moderator/reports/{id}', [$moderation, 'report']);

// Admin panel routes
$admin = new App\Controllers\AdminController();
$game = new App\Controllers\GameController();

// Register admin CRUD and analytics routes
$router->get('/admin/overview', [$admin, 'overview']);
$router->get('/admin/users', [$admin, 'users']);
$router->post('/admin/users/{id}', [$admin, 'saveUser']);
$router->get('/admin/categories', [$admin, 'categories']);
$router->post('/admin/categories', [$admin, 'saveCategory']);
$router->get('/admin/settings', [$admin, 'settings']);
$router->post('/admin/settings', [$admin, 'saveSettings']);
$router->post('/admin/staff/create', [$admin, 'createStaff']);
$router->post('/admin/staff/{id}/update', [$admin, 'updateStaff']);
$router->post('/admin/staff/{id}/permissions', [$admin, 'savePermissions']);
$router->post('/admin/users/{id}/delete', [$admin, 'deleteUser']);
$router->post('/admin/categories/{id}/delete', [$admin, 'deleteCategory']);
$router->get('/admin/jokes', [$admin, 'jokes']);
$router->post('/admin/jokes/{id}/delete', [$admin, 'deleteJoke']);
$router->get('/admin/reports', [$admin, 'reports']);

// AI Playground routes
$router->get('/playground', [$game, 'index']);
$router->post('/playground/start', [$game, 'start']);
$router->get('/playground/{id}', [$game, 'show']);
$router->post('/playground/{id}/turn', [$game, 'turn']);
$router->post('/playground/{id}/delete', [$game, 'delete']);

// User profile, avatar, password, and notifications
$profile = new App\Controllers\ProfileController();
$router->get('/profile', [$profile, 'index']);
$router->get('/profile/settings', [$profile, 'settings']);
$router->post('/profile', [$profile, 'update']);
$router->post('/profile/password', [$profile, 'password']);
$router->post('/profile/password/otp', [$profile, 'passwordOtp']);
$router->post('/profile/avatar', [$profile, 'avatar']);
$router->post('/profile/avatar/preset', [$profile, 'presetAvatar']);
$router->post('/profile/delete', [$profile, 'delete']);
$router->get('/notifications', [$profile, 'notifications']);
$router->post('/notifications/read-all', [$profile, 'readAll']);
$router->post('/notifications/{id}/read', [$profile, 'read']);

// The requested direct .php file declares its equivalent route in $jokeRoute.
$virtualPath = $jokeRoute ?? '/';
// Substitute dynamic {id} route placeholder from query parameter when present
if (isset($_GET['id'])) {
    $virtualPath = str_replace('{id}', (string) (int) $_GET['id'], $virtualPath);
}

// Read the HTTP request method with GET fallback
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Dispatch from the PHP entrypoint without relying on Apache rewrite rules.
$router->dispatchPath($virtualPath, $method);
