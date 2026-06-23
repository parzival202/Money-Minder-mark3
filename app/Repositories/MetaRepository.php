<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class MetaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function get(string $key): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function set(string $key, string $value): void
    {
        $this->pdo->prepare('REPLACE INTO meta (key, value) VALUES (?, ?)')->execute([$key, $value]);
    }

    public function getForUser(string $key, int $userId, string $default = ''): string
    {
        $storageKey = $this->buildKey($key, $userId);
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute([$storageKey]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string)$value;
    }

    public function setForUser(string $key, string $value, int $userId): void
    {
        $storageKey = $this->buildKey($key, $userId);
        $stmt = $this->pdo->prepare('REPLACE INTO meta (key, value) VALUES (?, ?)');
        $stmt->execute([$storageKey, $value]);
    }

    private function buildKey(string $key, int $userId): string
    {
        return $key . '_user_' . $userId;
    }
}
