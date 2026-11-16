<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Models\PasswordReset;
use App\Models\Profile;
use App\Services\SmtpMailer;
use PDOException;

/**
 * ProfileController — MVC controller for user/staff profile pages, settings, avatars, and notifications.
 *
 * Purpose:
 *   Manages profile viewing, metadata updates, binary/preset avatar uploads, OTP-based password changes,
 *   account deletion, and notification read-state endpoints (supporting both HTML redirects and AJAX JSON).
 * Connections:
 *   - Uses App\Models\Profile, App\Models\PasswordReset, and App\Services\SmtpMailer.
 *   - Renders profile/* or admin/staff-* templates depending on user role.
 * OOP:
 *   - Final controller class with a private authenticated-user ID guard.
 * Data Structures & Algorithms:
 *   - Avatar Validation: Checks upload status, <= 2 MB size, getimagesize() dimensions (32-3000 px),
 *     and finfo MIME type (JPG/PNG/WebP) before storing binary data in MySQL.
 */
final class ProfileController
{
    /**
     * Require an authenticated session and return the current user's ID.
     */
    private function id(): int
    {
        // Enforce authentication and return the signed-in user's ID.
        Auth::requireLogin();
        return (int) Auth::user()['id'];
    }

    /**
     * Render either the staff profile or the regular member profile with activity stats.
     */
    public function index(): void
    {
        // Resolve the authenticated user ID and instantiate the Profile model.
        $id = $this->id();
        $m = new Profile();

        // Render the simplified staff profile view for admins and moderators.
        if (Auth::hasRole('admin') || Auth::hasRole('moderator')) {
            View::render('admin/staff-profile', [
                'title' => 'Staff Profile · JOKÉ',
                'profile' => $m->detail($id),
            ]);
            return;
        }

        // Render the full member profile with stats, saved jokes, comments, and game history.
        View::render('profile/index', [
            'title' => 'My Profile · JOKÉ',
            'profile' => $m->detail($id),
            'stats' => $m->stats($id),
            'favorites' => $m->favorites($id),
            'comments' => $m->comments($id),
            'history' => $m->history($id),
        ]);
    }

    /**
     * Render the profile settings page appropriate for the user's role.
     */
    public function settings(): void
    {
        // Resolve the authenticated user ID.
        $id = $this->id();

        // Render staff settings for admins/moderators or standard profile settings for members.
        View::render(
            (Auth::hasRole('admin') || Auth::hasRole('moderator')) ? 'admin/staff-settings' : 'profile/settings',
            [
                'title' => 'Profile Settings · JOKÉ',
                'profile' => (new Profile())->detail($id),
            ]
        );
    }

    /**
     * Validate and save updated username, bio, and location fields.
     */
    public function update(): void
    {
        // Require login and verify the CSRF token.
        $id = $this->id();
        Csrf::enforce();

        // Read and trim the submitted profile fields.
        $name = trim((string) ($_POST['username'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));

        // Validate username characters/length and bio/location maximum lengths.
        if (!preg_match('/^[\p{L}\p{N}_]{3,40}$/u', $name) || mb_strlen($bio) > 500 || mb_strlen($location) > 100) {
            Session::flash('Username must contain 3–40 valid characters; bio max 500; location max 100 characters.');
            Url::redirect('/profile/settings');
        }

        // Persist the profile update, catching unique-username constraint violations.
        try {
            (new Profile())->save($id, $name, $bio, $location);
            Session::flash('Profile updated.');
        } catch (PDOException $e) {
            Session::flash('Username is already taken or your profile could not be saved.');
        }

        // Redirect back to the profile settings page.
        Url::redirect('/profile/settings');
    }

    /**
     * Validate an uploaded image file and store its binary contents in MySQL.
     */
    public function avatar(): void
    {
        // Require login and verify the CSRF token.
        $id = $this->id();
        Csrf::enforce();

        // Verify a single file was uploaded cleanly within the 2 MB size limit.
        $f = $_FILES['avatar'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($f['size'] ?? 0) > 2097152 || ($f['size'] ?? 0) < 1) {
            Session::flash('Please choose an image smaller than 2 MB.');
            Url::redirect('/profile/settings');
        }

        // Confirm the temporary path is a genuine HTTP POST uploaded file.
        $tmp = $f['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            Session::flash('Invalid upload.');
            Url::redirect('/profile/settings');
        }

        // Validate image dimensions (32x32 up to 3000x3000 pixels).
        $info = @getimagesize($tmp);
        if (!$info || $info[0] > 3000 || $info[1] > 3000 || $info[0] < 32 || $info[1] < 32) {
            Session::flash('Please upload a valid image (32–3000 px).');
            Url::redirect('/profile/settings');
        }

        // Inspect actual file magic bytes via finfo and map to allowed image types.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;

        // Ensure both finfo and getimagesize report an allowed JPG/PNG/WebP MIME type.
        if (!$ext || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            Session::flash('Only JPG, PNG and WebP images are allowed.');
            Url::redirect('/profile/settings');
        }

        // Store the actual binary image in MySQL (not a filesystem path).
        $binary = file_get_contents($tmp);
        if ($binary === false || strlen($binary) > 2097152) {
            Session::flash('Unable to read the uploaded image.');
            Url::redirect('/profile/settings');
        }

        // Persist the avatar BLOB in the database and flash feedback.
        try {
            (new Profile())->saveAvatarBinary($id, $binary, $mime);
            Session::flash('Profile photo updated.');
        } catch (\Throwable $e) {
            error_log('JOKE avatar upload failed: ' . $e->getMessage());
            Session::flash('Unable to update photo. Please import database/avatar_in_database.sql first.');
        }

        // Redirect back to profile settings.
        Url::redirect('/profile/settings');
    }

    /**
     * Save a selected preset avatar ID.
     */
    public function presetAvatar(): void
    {
        // Require login and verify the CSRF token.
        $id = $this->id();
        Csrf::enforce();

        // Save the chosen preset avatar index, flashing an error if out of range.
        try {
            (new Profile())->savePresetAvatar($id, (int) ($_POST['preset'] ?? 0));
            Session::flash('Profile avatar updated.');
        } catch (\Throwable $e) {
            Session::flash('Choose one of the available avatars.');
        }

        // Redirect back to profile settings.
        Url::redirect('/profile/settings');
    }

    // Kept for old bookmarks/forms: every password change now goes through email OTP.
    public function password(): void
    {
        // Forward legacy password change requests to the OTP flow.
        $this->passwordOtp();
    }

    /**
     * Send a 4-digit OTP to the authenticated user's email to initiate a password change.
     */
    public function passwordOtp(): void
    {
        // Require login and verify the CSRF token.
        $this->id();
        Csrf::enforce();

        // Read the authenticated user's normalized email address.
        $email = strtolower((string) Auth::user()['email']);

        try {
            // Generate a 4-digit OTP and send it via HTTPS/SMTP if not rate-limited.
            $otp = (new PasswordReset())->createForEmail($email);
            if ($otp !== null) {
                $_SESSION['reset_local_otp'] = SmtpMailer::isLocalDevFallback() ? $otp : null;
                (new SmtpMailer())->sendPasswordOtp($email, $otp);
            }

            // Initialize reset wizard session state with a 10-minute expiry.
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_expires_at'] = time() + 600;
            unset($_SESSION['verified_reset_id']);

            // Flash confirmation and redirect to the OTP verification page.
            Session::flash(!empty($_SESSION['reset_local_otp'])
                ? 'Local Test Mode (mail not configured): Your 4-digit OTP is ' . $_SESSION['reset_local_otp'] . '.'
                : 'A four-digit security code has been sent to your account email.');
            Url::redirect('/reset-password');
        } catch (\Throwable $e) {
            // Log mail/DB failures and redirect back to settings with an error notice.
            error_log('JOKE profile password OTP: ' . $e->getMessage());
            Session::flash('The security code could not be sent. Check the email configuration and try again.');
            Url::redirect('/profile/settings');
        }
    }

    /**
     * Confirm password and delete/anonymize the current user's account.
     */
    public function delete(): void
    {
        // Require login and verify the CSRF token.
        $id = $this->id();
        Csrf::enforce();

        // Require explicit deletion confirmation and a submitted password string.
        if (($_POST['confirm_delete'] ?? '') !== 'yes' || !is_string($_POST['password'] ?? null)) {
            Session::flash('Please confirm and enter your password.');
            Url::redirect('/profile/settings');
        }

        // Attempt account deletion, rejecting wrong passwords or the last active admin.
        if (!(new Profile())->deleteAccount($id, (string) $_POST['password'])) {
            Session::flash('Unable to delete account: incorrect password or this is the final administrator account.');
            Url::redirect('/profile/settings');
        }

        // Destroy the user's session and redirect to the home page.
        Auth::logout();
        Url::redirect('/');
    }

    /**
     * Redirect legacy /notifications requests to the profile page.
     */
    public function notifications(): void
    {
        // Redirect legacy notification URLs to the profile page.
        Url::redirect('/profile');
    }

    /**
     * Mark a single notification as read; returns JSON for AJAX requests or redirects back.
     */
    public function read(int $id): void
    {
        // Require login and verify the CSRF token.
        $user = $this->id();
        Csrf::enforce();

        // Mark the specified notification as read for the current user.
        $profile = new Profile();
        $profile->markRead($id, $user);

        // Return updated unread count as JSON when invoked via AJAX.
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'unread' => $profile->unreadCount($user)]);
            exit;
        }

        // Redirect standard form requests back to the referring page or profile.
        $back = $_SERVER['HTTP_REFERER'] ?? '/';
        if (str_contains($back, '/notifications')) {
            $back = '/profile';
        }

        Url::redirect($back);
    }

    /**
     * Mark all notifications for the current user as read; returns JSON for AJAX requests or redirects back.
     */
    public function readAll(): void
    {
        // Require login and verify the CSRF token.
        $user = $this->id();
        Csrf::enforce();

        // Mark all of the user's notifications as read.
        $profile = new Profile();
        $profile->markAllRead($user);

        // Return zero unread count as JSON when invoked via AJAX.
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'unread' => 0]);
            exit;
        }

        // Redirect standard form requests back to the referring page or profile.
        $back = $_SERVER['HTTP_REFERER'] ?? '/';
        if (str_contains($back, '/notifications')) {
            $back = '/profile';
        }

        Url::redirect($back);
    }
}
