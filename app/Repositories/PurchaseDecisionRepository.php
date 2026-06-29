<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class PurchaseDecisionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(
        int $userId,
        float $amount,
        ?int $categoryId,
        string $type,
        string $urgency,
        string $description,
        string $decision,
        string $reason
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_decisions (user_id, amount, category_id, type, urgency, description, decision, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $amount, $categoryId, $type, $urgency, $description, $decision, $reason]);
        return (int)$this->pdo->lastInsertId();
    }
}
