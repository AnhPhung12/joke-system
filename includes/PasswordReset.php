<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * PasswordReset — Security-hardened OTP reset model (`password_resets` table).
 *
 * Purpose:
 *   Generates, verifies, and consumes 4-digit one-time passcodes for password recovery.
 * Connections:
 *   - Used by PasswordResetController and ProfileController.
 *   - Paired with SmtpMailer for OTP email delivery and User for account lookups.
 * OOP:
 *   - Final model class holding a shared PDO connection.
 * Data Structures & Algorithms:
 *   - Generates CSPRNG 4-digit OTP via random_int() and stores only password_hash() digests.
 *   - Rate Limiting: Max 3 OTP requests per 15 minutes; max 5 failed verification attempts per OTP; 10-minute TTL.
 *   - Concurrency: Uses PDO transactions with SELECT ... FOR UPDATE row locking to prevent race conditions.
 */
final class PasswordReset
{
    // Shared PDO database connection for OTP operations.
    private PDO $db;

    public function __construct()
    {
        // Obtain the singleton PDO connection.
        $this->db = Database::connect();
    }

    /**
     * Create a new 4-digit OTP for an active account, enforcing a 3-per-15-minute rate limit.
     * Returns the plaintext OTP for email delivery, or null if ineligible or rate-limited.
     */
    public function createForEmail(string $email): ?string
    {
        // Look up the user by email and verify the account is active.
        $user = (new User())->findByEmail($email);
        if (!$user || $user['status'] !== 'active') {
            return null;
        }

        // Start a transaction to atomically check rate limits and rotate OTPs.
        $this->db->beginTransaction();

        try {
            // Count OTPs issued for this user within the last 15 minutes.
            $rate = $this->db->prepare(
                'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );
            $rate->execute([(int) $user['id']]);

            // Enforce the maximum of 3 OTP requests per 15-minute window.
            if ((int) $rate->fetchColumn() >= 3) {
                $this->db->commit();
                return null;
            }

            // Invalidate any previously issued unused OTPs for this user.
            $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                ->execute([(int) $user['id']]);

            // Generate a cryptographically secure 4-digit OTP.
            $otp = (string) random_int(1000, 9999);
            // Store the hashed OTP with a 10-minute expiration window.
            $insert = $this->db->prepare(
                'INSERT INTO password_resets(user_id, otp_hash, expires_at) VALUES(?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))'
            );
            $insert->execute([(int) $user['id'], password_hash($otp, PASSWORD_DEFAULT)]);

            // Commit the new OTP record and return the plaintext code for emailing.
            $this->db->commit();
            return $otp;
        } catch (\Throwable $e) {
            // Roll back the transaction if an error occurs while active.
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Verify a 4-digit OTP for the given email.
     * Increments the attempt counter on mismatch; marks the reset row verified on success.
     */
    public function verify(string $email, string $otp): ?int
    {
        // Start a transaction so row locking and attempt counting are atomic.
        $this->db->beginTransaction();

        try {
            // Verify the target account exists and is active.
            $user = (new User())->findByEmail($email);
            if (!$user || $user['status'] !== 'active') {
                $this->db->rollBack();
                return null;
            }

            // Lock the latest unused reset record for this user.
            $stmt = $this->db->prepare(
                'SELECT * FROM password_resets WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([(int) $user['id']]);
            $reset = $stmt->fetch();

            // Reject if missing, expired, or if 5 failed attempts have been reached.
            if (!$reset || strtotime((string) $reset['expires_at']) < time() || (int) $reset['attempts'] >= 5) {
                $this->db->rollBack();
                return null;
            }

            // Increment the attempt counter and commit on OTP hash mismatch.
            if (!password_verify($otp, (string) $reset['otp_hash'])) {
                $this->db->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')
                    ->execute([(int) $reset['id']]);
                $this->db->commit();
                return null;
            }

            // Mark the OTP record as verified and commit the transaction.
            $this->db->prepare('UPDATE password_resets SET verified_at = NOW() WHERE id = ?')
                ->execute([(int) $reset['id']]);
            $this->db->commit();

            // Return the verified reset record ID.
            return (int) $reset['id'];
        } catch (\Throwable $e) {
            // Roll back on any unexpected exception.
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Complete the password reset using a verified reset ID and invalidate all open reset tokens.
     */
    public function complete(int $resetId, string $newPassword): bool
    {
        // Start a transaction to atomically update the password and consume tokens.
        $this->db->beginTransaction();

        try {
            // Lock the reset row joined with user status to ensure validity.
            $stmt = $this->db->prepare(
                'SELECT pr.*, u.status FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.id = ? FOR UPDATE'
            );
            $stmt->execute([$resetId]);
            $reset = $stmt->fetch();

            // Abort if the reset record is missing, inactive, unverified, used, or expired.
            if (
                !$reset
                || $reset['status'] !== 'active'
                || $reset['verified_at'] === null
                || $reset['used_at'] !== null
                || strtotime((string) $reset['expires_at']) < time()
            ) {
                $this->db->rollBack();
                return false;
            }

            // Update the user's password hash using PHP's default strong algorithm.
            $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $reset['user_id']]);

            // Mark all open password reset tokens for this user as consumed.
            $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
                ->execute([(int) $reset['user_id']]);

            // Commit the password update and token invalidation.
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            // Roll back the transaction if an error occurs.
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
