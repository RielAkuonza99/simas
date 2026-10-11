<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = app_env('DB_HOST') ?: 'localhost';
        $database = app_env('DB_NAME') ?: 'surat_masuk';
        $username = app_env('DB_USER');
        $password = app_env('DB_PASS');

        if ($username === false) {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $database);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $username, $password === false ? '' : $password, $options);
        } catch (PDOException $error) {
            throw new RuntimeException('Database connection failed.', 0, $error);
        }

        return self::$pdo;
    }
}