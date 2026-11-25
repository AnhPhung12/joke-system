<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Session, Url, View};
use App\Models\User;
use App\Services\SmtpMailer;
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
        // Redirect moderators straight to Manage Jokes; admins go to the first permitted tab
        if (!Auth::hasRole('admin') && Auth::can('jokes.manage')) {
            Url::redirect('/admin/jokes');
        }
        foreach ([
            ['analytics.view', '/admin/overview'],
            ['users.view', '/admin/users'],
            ['categories.manage', '/admin/categories'],
            ['settings.manage', '/admin/settings'],
            ['jokes.manage', '/admin/jokes'],
            ['reports.manage', '/admin/reports'],
        ] as [$perm, $route]) {
            if (Auth::can($perm)) {
                Url::redirect($route);
            }
        }
        Url::redirect('/admin/jokes');
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

        // Check for an active, unexpired registration OTP session and clear expired state
        $pending = $_SESSION['register_otp'] ?? null;
        $otpPending = is_array($pending) && time() <= (int) ($pending['expires_at'] ?? 0);
        if (!$otpPending) {
            unset($_SESSION['register_otp']);
        }

        // Render the registration page (Step 1 form or Step 2 OTP verification)
        View::render('auth/register', [
            'title'        => 'Sign up',
            'otpPending'   => $otpPending,
            'otpEmail'     => $otpPending ? (string) ($pending['email'] ?? '') : '',
            'otpExpiresAt' => $otpPending ? (int) ($pending['expires_at'] ?? 0) : 0,
            'localOtp'     => $otpPending ? ($pending['local_otp'] ?? null) : null,
        ]);
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
     * Validate input, verify email via 4-digit OTP, and register a new user account.
     */
    public function register(): void
    {
        // Validate CSRF token on registration submission
        Csrf::enforce();

        // Dispatch based on the submitted registration step action
        $action = (string) ($_POST['action'] ?? 'send_otp');

        // Clear pending OTP state and restart registration when cancelled
        if ($action === 'cancel_otp') {
            unset($_SESSION['register_otp']);
            Url::redirect('/register');
        }

        // Regenerate and resend a fresh 4-digit OTP for an active pending registration
        if ($action === 'resend_otp') {
            $pending = $_SESSION['register_otp'] ?? null;
            if (!is_array($pending) || empty($pending['email'])) {
                Session::flash('Your registration session expired. Please fill in your details again.');
                Url::redirect('/register');
            }
            // Issue a new 4-digit code with a fresh 10-minute expiry and reset attempt counter
            $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $pending['code_hash'] = hash('sha256', $otp);
            $pending['expires_at'] = time() + 600;
            $pending['attempts'] = 0;
            $pending['local_otp'] = SmtpMailer::isLocalDevFallback() ? $otp : null;
            $_SESSION['register_otp'] = $pending;

            // Send the new verification code via configured mail transport
            try {
                (new SmtpMailer())->sendPasswordOtp(
                    (string) $pending['email'],
                    $otp,
                    'JOKÉ account verification code',
                    'JOKÉ Email Verification Code'
                );
                Session::flash(!empty($pending['local_otp'])
                    ? 'Local Test Mode (mail not configured): Your 4-digit OTP is ' . $pending['local_otp'] . '.'
                    : 'A new 4-digit verification code has been sent to ' . $pending['email'] . '.');
            } catch (\Throwable $e) {
                error_log('JOKE register OTP resend: ' . $e->getMessage());
                Session::flash('Could not resend verification code. Please try again.');
            }
            Url::redirect('/register');
        }

        // Verify the submitted 4-digit OTP and create the user account on match
        if ($action === 'verify_otp') {
            $pending = $_SESSION['register_otp'] ?? null;
            $otp = trim((string) ($_POST['otp'] ?? ''));

            // Reject missing or expired registration OTP sessions
            if (!is_array($pending) || time() > (int) ($pending['expires_at'] ?? 0)) {
                unset($_SESSION['register_otp']);
                Session::flash('Verification code expired. Please sign up again.');
                Url::redirect('/register');
            }

            // Validate 4-digit OTP against stored hash and lock out after 5 failed attempts
            if (!preg_match('/^\d{4}$/', $otp) || !hash_equals((string) ($pending['code_hash'] ?? ''), hash('sha256', $otp))) {
                $pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
                if ($pending['attempts'] >= 5) {
                    unset($_SESSION['register_otp']);
                    Session::flash('Too many incorrect OTP attempts. Please register again.');
                } else {
                    $_SESSION['register_otp'] = $pending;
                    Session::flash('Incorrect 4-digit verification code.');
                }
                Url::redirect('/register');
            }

            // Persist the verified user account and handle duplicate-key race conditions
            try {
                (new User())->create(
                    (string) $pending['username'],
                    (string) $pending['email'],
                    (string) $pending['pass_hash'],
                    (string) ($pending['location'] ?? 'Vietnam'),
                    true
                );
                unset($_SESSION['register_otp']);
            } catch (PDOException $e) {
                unset($_SESSION['register_otp']);
                if ($e->getCode() === '23000') {
                    Session::flash('Username or email already exists.');
                } else {
                    error_log($e->getMessage());
                    Session::flash('Registration is unavailable at the moment.');
                }
                Url::redirect('/register');
            }

            // Redirect newly registered user to sign in
            Session::flash('Registration successful! Please sign in.');
            Url::redirect('/login');
        }

        // Step 1: Validate registration fields, check uniqueness, and send 4-digit OTP
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $location = trim((string) ($_POST['location'] ?? ''));
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

        // Reject registration early if the username or email is already taken
        if ((new User())->exists($username, $email)) {
            Session::flash('Username or email already exists.');
            Url::redirect('/register');
        }

        // Generate a 4-digit OTP and store pending registration state in session for 10 minutes
        $otp = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $localOtp = SmtpMailer::isLocalDevFallback() ? $otp : null;
        $_SESSION['register_otp'] = [
            'username'   => $username,
            'email'      => $email,
            'location'   => $location !== '' ? mb_substr($location, 0, 120) : 'Vietnam',
            'pass_hash'  => password_hash($password, PASSWORD_DEFAULT),
            'code_hash'  => hash('sha256', $otp),
            'expires_at' => time() + 600,
            'attempts'   => 0,
            'local_otp'  => $localOtp,
        ];

        // Email the verification code or roll back pending session state on failure
        try {
            (new SmtpMailer())->sendPasswordOtp(
                $email,
                $otp,
                'JOKÉ account verification code',
                'JOKÉ Email Verification Code'
            );
            Session::flash($localOtp !== null
                ? 'Local Test Mode (mail not configured): Your 4-digit OTP is ' . $localOtp . '.'
                : 'A 4-digit verification code has been sent to ' . $email . '.');
        } catch (\Throwable $e) {
            unset($_SESSION['register_otp']);
            error_log('JOKE register OTP: ' . $e->getMessage());
            Session::flash('Could not send verification email. Please check email settings and try again.');
        }

        // Redirect back to render the OTP verification stage or flash error
        Url::redirect('/register');
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
