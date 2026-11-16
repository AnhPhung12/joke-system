<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database — Singleton PDO Connection Factory.
 *
 * Purpose: Establishes and caches a single UTF-8 (`utf8mb4`) PDO MySQL
 *          connection per request with strict error reporting and native
 *          prepared statements.
 * Connections: Reads credentials from `config/config.php`; consumed by all
 *              models in `App\Models\*` and `App\Core\Auth`.
 * Data Structures & Security:
 *   - Static singleton `$connection` avoids duplicate TCP/MySQL handshakes.
 *   - Disables `PDO::ATTR_EMULATE_PREPARES` so parameter binding is enforced by MySQL.
 */
final class Database
{
    // Cached singleton PDO instance for the current request
    private static ?PDO $connection = null;

    /**
     * Return the cached PDO instance or open a new connection using `config/config.php`.
     */
    public static function connect(): PDO
    {
        // Reuse existing connection if already established
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Load application configuration array
        $c = require dirname(__DIR__) . '/config/config.php';
        // Extract database connection settings
        $d = $c['db'];
        // Build MySQL DSN string with utf8mb4 charset
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['name']);

        try {
            // Configure PDO options for strict error reporting, associative fetch, and native prepares
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            // Enable TLS/SSL automatically for cloud MySQL hosts such as TiDB Cloud
            if (!in_array($d['host'], ['localhost', '127.0.0.1', '::1'], true)) {
                // Use configured CA bundle when present, or skip server cert verification as fallback
                $caPath = getenv('JOKE_DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
                if (is_file($caPath)) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                } else {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
            // Instantiate PDO connection and align session time zone to UTC+7
            self::$connection = new PDO($dsn, $d['user'], $d['pass'], $options);
            self::$connection->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            // Log detailed connection error without exposing credentials
            error_log($e->getMessage());
            // Throw a generic runtime exception for callers
            throw new RuntimeException('Could not connect to MySQL. Please check your database configuration.');
        }

        // Return the newly created PDO instance
        return self::$connection;
    }
}
