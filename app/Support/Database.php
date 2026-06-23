<?php

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dbPath = (string)Config::get('database.path');
        if ($dbPath === '') {
            throw new RuntimeException('Database path is not configured.');
        }

        $directory = dirname($dbPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create database directory: ' . $directory);
        }

        try {
            self::$connection = new PDO('sqlite:' . $dbPath);
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->exec('PRAGMA foreign_keys = ON;');
        } catch (PDOException $e) {
            throw new RuntimeException('Erreur DB: ' . $e->getMessage(), 0, $e);
        }

        return self::$connection;
    }
}
