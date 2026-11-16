<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\PasswordReset;
use App\Services\SmtpMailer;

/**
 * PasswordResetController — MVC controller for the 3-step OTP password recovery flow.
 *
 * Purpose:
 *   Coordinates requesting a 4-digit email OTP, verifying it, and setting a new password.
 * Connections:
 *   - Uses App\Models\PasswordReset for token lifecycle and App\Services\SmtpMailer for delivery.
 *   - Renders auth/forgot-password and auth/reset-password templates via View::render().
 * OOP:
 *   - Final stateless controller class.
 * Data Structures & Algorithms:
 *   - Uses session state ($_SESSION['reset_email'], 'reset_expires_at', 'verified_reset_id')
 *     to track the multi-step wizard without leaking account existence on OTP requests.
 */
final class PasswordResetController
{
    /**
     * Render the "Forgot password" email submission form.
     */
    public function forgotForm(): void
    {
        // Render the email entry form for initiating a password reset.
        View::render('auth/forgot-password', ['title' => 'Forgot password']);
    }

    /**
     * Render the OTP verification or new-password entry stage depending on session state.
     */
    public function resetForm(): void
    {
        // Redirect back to the email request step if no reset session is active.
        if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_expires_at'])) {
            Session::flash('Request a reset code first.');
            Url::redirect('/forgot-password');
        }

        // Choose between the OTP verification stage and the new-password stage.
        $stage = isset($_SESSION['verified_reset_id']) ? 'password' : 'otp';

        // Render the reset-password template with the current stage and expiration timestamp.
        View::render('auth/reset-password', [
            'title' => 'Reset password',
            'stage' => $stage,
            'expiresAt' => (int) $_SESSION['reset_expires_at'],
        ]);
    }

    /**
     * Handle OTP request submission, sending an email if an active account exists.
     * Always shows a generic confirmation message to prevent account enumeration.
     */
    public function requestOtp(): void
    {
        // Validate the CSRF token on POST submission.
        Csrf::enforce();

        // Normalize the submitted email address and prepare a generic response message.
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $message = 'If an active account uses that email, a four-digit reset code has been sent.';

        // Only generate and email an OTP when the input is a syntactically valid email.
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                // Create a new OTP for the active account if not rate-limited.
                $otp = (new PasswordReset())->createForEmail($email);
                // Send the OTP email when a code was generated.
                if ($otp !== null) {
                    $_SESSION['reset_local_otp'] = SmtpMailer::isLocalDevFallback() ? $otp : null;
                    (new SmtpMailer())->sendPasswordOtp($email, $otp);
                    if (!empty($_SESSION['reset_local_otp'])) {
                        $message = 'Local Test Mode (mail not configured): Your 4-digit OTP is ' . $_SESSION['reset_local_otp'] . '.';
                    }
                }

                // Store reset wizard state in the session with a 10-minute expiry.
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_expires_at'] = time() + 600;
                unset($_SESSION['verified_reset_id']);
            } catch (\Throwable $e) {
                // Log delivery or DB errors without exposing details to the user.
                error_log('JOKE password reset request: ' . $e->getMessage());
            }
        }

        // Flash the generic confirmation and redirect to the OTP verification page.
        Session::flash($message);
        Url::redirect('/reset-password');
    }

    /**
     * Verify the submitted 4-digit OTP against the active reset record.
     */
    public function verifyOtp(): void
    {
        // Validate the CSRF token on POST submission.
        Csrf::enforce();

        // Read the target email from session and the submitted 4-digit OTP.
        $email = (string) ($_SESSION['reset_email'] ?? '');
        $otp = trim((string) ($_POST['otp'] ?? ''));

        // Verify the OTP only if the email and 4-digit format are valid.
        $id = (filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^\d{4}$/', $otp))
            ? (new PasswordReset())->verify($email, $otp)
            : null;

        // Flash an error and return to the OTP form when verification fails.
        if ($id === null) {
            Session::flash('The code is invalid, expired or has been used too many times.');
            Url::redirect('/reset-password');
        }

        // Persist the verified reset ID in session and advance to the password stage.
        $_SESSION['verified_reset_id'] = $id;
        Url::redirect('/reset-password');
    }

    /**
     * Validate and apply the new password after OTP verification.
     */
    public function resetPassword(): void
    {
        // Validate the CSRF token on POST submission.
        Csrf::enforce();

        // Read the new password, confirmation, and verified reset ID from session.
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $id = (int) ($_SESSION['verified_reset_id'] ?? 0);

        // Enforce verified session state, password length bounds (10-128), and confirmation match.
        if ($id < 1 || strlen($password) < 10 || strlen($password) > 128 || !hash_equals($password, $confirm)) {
            Session::flash('Enter matching password values between 10 and 128 characters.');
            Url::redirect('/reset-password');
        }

        // Apply the new password and clear all reset wizard session keys.
        $ok = (new PasswordReset())->complete($id, $password);
        unset($_SESSION['reset_email'], $_SESSION['reset_expires_at'], $_SESSION['verified_reset_id'], $_SESSION['reset_local_otp']);

        // Flash the outcome and redirect to login on success or back to reset on failure.
        Session::flash($ok ? 'Password reset. You can now sign in.' : 'The code is invalid, expired or has been used too many times.');
        Url::redirect($ok ? '/login' : '/reset-password');
    }
}
