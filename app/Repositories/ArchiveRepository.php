<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class ArchiveRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function save(int $userId, string $monthYear, array $data, float $totalExpenses): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO archives (user_id, month_year, data_json, total_expenses) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $monthYear, json_encode($data), $totalExpenses]);
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM archives WHERE user_id = ? ORDER BY month_year DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForCycle(int $userId, string $monthYear, string $legacyMonthYear): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM archives WHERE user_id = ? AND month_year IN (?, ?) ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId, $monthYear, $legacyMonthYear]);
        $archive = $stmt->fetch(PDO::FETCH_ASSOC);

        return $archive ?: null;
    }
}
