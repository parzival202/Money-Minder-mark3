<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class DailyCheckinRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findForDate(int $userId, string $checkinDate): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM daily_checkins WHERE user_id = ? AND checkin_date = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId, $checkinDate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $userId, string $checkinDate, string $status, string $note = ''): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO daily_checkins (user_id, checkin_date, status, note) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $checkinDate, $status, $note]);
        return (int)$this->pdo->lastInsertId();
    }
}
