<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Session, Url, View};
use App\Models\User;
use PDOException;

/**
 * AuthController — MVC Controller for Registration, User Login, Staff Login & Logout.
 *
 * Purpose: Manages authentication forms and POST handlers for regular users
 *          and staff (moderators/admins).
 * Connections: Uses `App\Models\User` for DB persistence and
 *              `App\Core\{Auth, Csrf, Session, Url, View}` for session/response handling.
 * Security: Enforces CSRF tokens, regex/email validation, `password_verify()`,
 *           and active-status checks before issuing authenticated sessions.
 */
final class AuthController
{
    /**
     * Render the public user login form, redirecting already-authenticated users.
     */
    public function loginForm(): void
    {
        // Redirect signed-in staff to home (which routes to their admin panel)
        if (Auth::hasRole('admin') || Auth::hasRole('moderator')) {
            Url::redirect('/');
        }

        // Redirect already-signed-in regular users to their dashboard
        if (Auth::user()) {
            Url::redirect('/dashboard');
        }

        // Render the public login view
        View::render('auth/login', ['title' => 'Sign in']);
    }

    /**
     * Render the dedicated staff login form, redirecting already-signed-in staff.
     */
    public function staffLoginForm(): void
    {
        // Redirect staff who already have an active session to the admin overview
        if (Auth::user() && (Auth::hasRole('admin') || Auth::hasRole('moderator'))) {
            Url::redirect('/admin/overview');
        }

        // Render the staff login template
        View::render('auth/staff-login', ['title' => 'Staff Login · JOKÉ']);
    }

    /**
     * Authenticate a staff member (admin or moderator) and redirect to the admin panel.
     */
    public function staffLogin(): void
    {
        // Validate CSRF token on login submission
        Csrf::enforce();

        // Normalize email and read submitted password
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        // Look up user record by email
        $user = (new User())->findByEmail($email);

        // Verify account existence, active status, and password hash
        if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
            Session::flash('Invalid login credentials.');
            Url::redirect('/staff/login');
        }

        // Confirm the account holds an administrative or moderator role
        $roles = (new User())->findById((int) $user['id'])['roles'] ?? [];
        if (!in_array('admin', $roles, true) && !in_array('moderator', $roles, true)) {
            Session::flash('This account does not have staff access.');
            Url::redirect('/staff/login');
        }

        // Start authenticated session and record login timestamp
        Auth::login($user);
        (new User())->touchLogin((int) $user['id']);
        // Redirect staff to the admin overview
        Url::redirect('/admin/overview');
    }

    /**
     * Render the public registration form for guests.
     */
    public function registerForm(): void
    {
        // Redirect already-authenticated users to their dashboard
        if (Auth::user()) {
            Url::redirect('/dashboard');
        }

        // Render the registration page
        View::render('auth/register', ['title' => 'Sign up']);
    }

    /**
     * Authenticate a regular user and redirect to the home feed.
     */
    public function login(): void
    {
        // Validate CSRF token on login submission
        Csrf::enforce();

        // Normalize email and read submitted password
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        // Look up user record by email
        $user = (new User())->findByEmail($email);

        // Reject invalid credentials or suspended accounts
        if (!$user || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') {
            Session::flash('Invalid email or password, or the account is suspended.');
            Url::redirect('/login');
        }

        // Start authenticated session and record login timestamp
        Auth::login($user);
        (new User())->touchLogin((int) $user['id']);
        // Redirect to the home page
        Url::redirect('/');
    }

    /**
     * Validate input and register a new user account.
     */
    public function register(): void
    {
        // Validate CSRF token on registration submission
        Csrf::enforce();

        // Sanitize and normalize submitted registration fields
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        // Validate Unicode alphanumeric/underscore username, email, and password length/match
        if (
            !preg_match('/^[\p{L}\p{N}_]{3,40}$/u', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 10
            || strlen($password) > 128
            || $password !== $confirm
        ) {
            Session::flash('Check your username (3–40 characters), email and matching password (10–128 characters).');
            Url::redirect('/register');
        }

        try {
            // Persist the new user account in the database
            (new User())->create($username, $email, $password);
        } catch (PDOException $e) {
            // SQLSTATE 23000 indicates a duplicate username or email constraint violation
            if ($e->getCode() === '23000') {
                Session::flash('Username or email already exists.');
            } else {
                error_log($e->getMessage());
                Session::flash('Registration is unavailable at the moment.');
            }

            // Redirect back to registration on failure
            Url::redirect('/register');
        }

        // Notify user of success and redirect to sign-in page
        Session::flash('Registration successful! Please sign in.');
        Url::redirect('/login');
    }

    /**
     * Terminate the current user session and redirect home.
     */
    public function logout(): void
    {
        // Enforce CSRF protection on logout POST
        Csrf::enforce();
        // Remember whether the user was staff or logging out from an admin page
        $wasStaff = Auth::hasRole('admin') || Auth::hasRole('moderator');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $fromAdmin = $wasStaff || (bool) preg_match('#/(?:admin|moderator)(?:/|$|\.)#', $referer);
        // Destroy the active session
        Auth::logout();
        // Redirect staff back to the admin/staff login page, and regular users home
        Url::redirect($fromAdmin ? '/staff/login' : '/');
    }
}
