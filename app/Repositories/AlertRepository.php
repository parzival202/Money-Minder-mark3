<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class AlertRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(int $userId, string $type, string $message, bool $seen = false): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO alerts (user_id, type, message, seen) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $type, $message, $seen ? 1 : 0]);

        return (int)$this->pdo->lastInsertId();
    }

    public function allForUser(int $userId, bool $onlyUnseen = false): array
    {
        if ($onlyUnseen) {
            $stmt = $this->pdo->prepare('SELECT * FROM alerts WHERE user_id = ? AND seen = 0 ORDER BY created_at DESC');
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM alerts WHERE user_id = ? ORDER BY created_at DESC');
        }

        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSeen(int $userId, int $alertId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE alerts SET seen = 1 WHERE id = ? AND user_id = ?');
        return $stmt->execute([$alertId, $userId]);
    }

    public function markAllSeen(int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE alerts SET seen = 1 WHERE user_id = ?');
        return $stmt->execute([$userId]);
    }

    public function clearAll(int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM alerts WHERE user_id = ?');
        return $stmt->execute([$userId]);
    }
}
