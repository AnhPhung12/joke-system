<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SmtpMailer — Lightweight raw-socket SMTP client with STARTTLS and AUTH LOGIN support.
 *
 * Purpose:
 *   Sends 4-digit password reset OTP emails over TLS without external composer dependencies.
 *   Credentials are supplied only via environment variables loaded in config/config.php.
 * Connections:
 *   - Reads mail configuration from dirname(__DIR__) . '/config/config.php'.
 *   - Called by PasswordResetController and ProfileController.
 * OOP:
 *   - Final service class encapsulating socket command/response helpers.
 * Data Structures & Algorithms:
 *   - Implements a synchronous SMTP state machine (220 -> EHLO -> STARTTLS -> AUTH LOGIN -> MAIL -> RCPT -> DATA -> QUIT)
 *     reading multi-line SMTP responses until the 4th character is no longer '-'.
 */
final class SmtpMailer
{
    /**
     * Check whether an HTTPS mail API/webhook or SMTP credentials are configured.
     */
    public static function isConfigured(): bool
    {
        // Treat HTTPS mail API or webhook environment variables as configured.
        if (trim((string) (getenv('JOKE_BREVO_API_KEY') ?: '')) !== '' || trim((string) (getenv('JOKE_MAIL_WEBHOOK_URL') ?: '')) !== '') {
            return true;
        }
        // Otherwise check whether SMTP credentials and sender address are set in config.
        $config = require dirname(__DIR__) . '/config/config.php';
        $mail = $config['mail'] ?? [];
        return trim((string) ($mail['username'] ?? '')) !== ''
            && trim((string) ($mail['password'] ?? '')) !== ''
            && trim((string) ($mail['from_email'] ?? '')) !== '';
    }

    /**
     * Returns true only on localhost when no mail transport is configured.
     */
    public static function isLocalDevFallback(): bool
    {
        // Never use local dev fallback when any mail transport is configured.
        if (self::isConfigured()) {
            return false;
        }
        // Normalize the request host (strip port) and allow fallback only on loopback addresses.
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Send the 4-digit OTP via HTTPS API/webhook (Render-compatible) or SMTP (local/VPS).
     */
    public function sendPasswordOtp(string $to, string $code, string $subject = 'JOKÉ password reset code', string $heading = 'JOKÉ Password Reset Code'): void
    {
        // On localhost without mail credentials, skip network delivery so Local Test Mode banner is shown.
        if (self::isLocalDevFallback()) {
            return;
        }

        // Load SMTP credentials and server settings from application config.
        $config = require dirname(__DIR__) . '/config/config.php';
        $mail = $config['mail'];
        $fromName = trim((string) ($mail['from_name'] ?? 'JOKÉ'));
        $fromEmail = trim((string) ($mail['from_email'] ?? '')) ?: 'noreply@example.com';
        $textBody = "Your JOKÉ verification code is: {$code}\r\n\r\nIt expires in 10 minutes. Do not share this code with anyone.";
        $htmlBody = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#1e1b24;color:#f5f5f5;border-radius:12px;">'
            . '<h2 style="margin:0 0 12px;color:#ffd166;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p style="margin:0 0 16px;line-height:1.6;">Use this 4-digit verification code (valid for 10 minutes):</p>'
            . '<div style="font-size:32px;font-weight:bold;letter-spacing:10px;text-align:center;padding:16px;background:rgba(255,209,102,0.14);border:1px solid rgba(255,209,102,0.35);border-radius:10px;color:#ffd166;margin-bottom:16px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<p style="margin:0;font-size:12px;color:#b5b5b5;">Do not share this code with anyone.</p></div>';

        // 1. Prefer HTTPS Brevo API when JOKE_BREVO_API_KEY is set.
        $brevoKey = trim((string) (getenv('JOKE_BREVO_API_KEY') ?: ''));
        if ($brevoKey !== '') {
            $payload = json_encode([
                'sender'      => ['name' => $fromName, 'email' => $fromEmail],
                'to'          => [['email' => $to, 'name' => $to]],
                'subject'     => $subject,
                'htmlContent' => $htmlBody,
                'textContent' => $textBody,
            ], JSON_UNESCAPED_UNICODE);
            $this->postJson('https://api.brevo.com/v3/smtp/email', (string) $payload, [
                'api-key: ' . $brevoKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            return;
        }

        // 2. Or use an HTTPS Mail Webhook (e.g. a dedicated Google Apps Script Web App) when JOKE_MAIL_WEBHOOK_URL is set.
        $webhookUrl = trim((string) (getenv('JOKE_MAIL_WEBHOOK_URL') ?: ''));
        if ($webhookUrl !== '') {
            $payload = json_encode([
                'to'       => $to,
                'toName'   => $to,
                'fromName' => $fromName,
                'subject'  => $subject,
                'html'     => $htmlBody,
                'text'     => $textBody,
            ], JSON_UNESCAPED_UNICODE);
            $this->postJson($webhookUrl, (string) $payload, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);
            return;
        }

        // 3. Fallback to raw SMTP socket (STARTTLS).
        // Ensure required SMTP credentials and sender address are configured.
        if ($mail['username'] === '' || $mail['password'] === '' || $mail['from_email'] === '') {
            throw new \RuntimeException('Email service is not configured.');
        }

        // Open a TCP socket connection to the SMTP server with a 15-second timeout.
        $socket = stream_socket_client(
            'tcp://' . $mail['host'] . ':' . $mail['port'],
            $errno,
            $error,
            15,
            STREAM_CLIENT_CONNECT
        );

        // Abort if the TCP connection could not be established.
        if (!$socket) {
            throw new \RuntimeException('Could not connect to the email service.');
        }

        // Apply a 15-second read/write timeout on the open socket stream.
        stream_set_timeout($socket, 15);

        try {
            // Wait for the SMTP 220 greeting and upgrade the connection via STARTTLS.
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO joke.local', [250]);
            $this->command($socket, 'STARTTLS', [220]);

            // Enable TLS encryption on the socket before sending credentials.
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('Could not secure the email connection.');
            }

            // Re-identify over TLS, authenticate via AUTH LOGIN, and start the mail transaction.
            $this->command($socket, 'EHLO joke.local', [250]);
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($mail['username']), [334]);
            $this->command($socket, base64_encode($mail['password']), [235]);
            $this->command($socket, 'MAIL FROM:<' . $mail['from_email'] . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            // Build the plain-text UTF-8 email headers, body, and SMTP end-of-data terminator.
            $subject = 'JOKÉ password reset code';
            $body = "Your JOKÉ password reset code is: {$code}\r\n\r\nIt expires in 10 minutes. Do not share this code with anyone.";
            $message = "From: " . $mail['from_name'] . " <" . $mail['from_email'] . ">\r\n"
                . "To: <{$to}>\r\n"
                . "Subject: {$subject}\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . "{$body}\r\n.";

            // Transmit the email payload and close the SMTP session cleanly.
            $this->command($socket, $message, [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            // Always close the socket handle even if an exception is thrown.
            fclose($socket);
        }
    }

    /**
     * Dispatch JSON payload over HTTPS POST via cURL (handling Apps Script 302 redirects).
     */
    private function postJson(string $url, string $jsonPayload, array $headers): void
    {
        // Require the cURL extension for outbound HTTPS API/webhook calls.
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL extension is required for HTTPS email delivery.');
        }

        // Configure a 20-second POST request without automatic redirect following.
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
        ]);

        // Execute the request and collect response body, error, status code, and redirect URL.
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        // Abort if cURL failed at the transport layer.
        if ($body === false) {
            throw new \RuntimeException('Email API error: ' . $err);
        }

        // Follow 3xx redirects via GET (required for Google Apps Script Web App webhooks).
        if ($code >= 300 && $code < 400 && $redirectUrl !== '') {
            $ch2 = curl_init($redirectUrl);
            curl_setopt_array($ch2, [
                CURLOPT_HTTPGET        => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
            ]);
            $body2 = curl_exec($ch2);
            $err2 = curl_error($ch2);
            $code2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            if ($body2 === false) {
                throw new \RuntimeException('Email API redirect error: ' . $err2);
            }
            $body = $body2;
            $code = $code2;
        }

        // Reject non-2xx HTTP responses with a truncated error message.
        if ($code < 200 || $code >= 300) {
            $decoded = json_decode((string) $body, true);
            $msg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? (string) $body) : (string) $body;
            throw new \RuntimeException('Email API returned HTTP ' . $code . ': ' . mb_substr((string) $msg, 0, 200));
        }

        // Check webhook JSON payloads that report application-level failure via ok === false.
        $decoded = json_decode((string) $body, true);
        if (is_array($decoded) && isset($decoded['ok']) && $decoded['ok'] === false) {
            throw new \RuntimeException('Email webhook error: ' . (string) ($decoded['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Write an SMTP command line and assert the expected numeric reply code(s).
     */
    private function command($socket, string $command, array $expected): void
    {
        // Send the CRLF-terminated SMTP command and verify the server's reply code.
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /**
     * Read single- or multi-line SMTP responses and verify the 3-digit status code.
     */
    private function expect($socket, array $expected): void
    {
        // Accumulate response lines until the continuation hyphen is absent.
        $response = '';

        do {
            // Read up to 2048 bytes per response line from the SMTP socket.
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new \RuntimeException('Email service did not respond.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        // Parse the 3-digit SMTP status code and verify it matches expected codes.
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('Email service rejected the request (' . $code . ').');
        }
    }
}
