<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class ExpenseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function allForUser(int $userId, ?string $monthYear = null): array
    {
        if ($monthYear) {
            $stmt = $this->pdo->prepare("SELECT * FROM expenses WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC, id DESC");
            $stmt->execute([$userId, $monthYear]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalForCategory(int $userId, string $category): float
    {
        $stmt = $this->pdo->prepare('SELECT SUM(amount) FROM expenses WHERE user_id = ? AND category = ?');
        $stmt->execute([$userId, $category]);
        return (float)($stmt->fetchColumn() ?? 0);
    }

    public function create(int $userId, array $expense): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO expenses (user_id, date, category, description, amount) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $expense['date'],
            $expense['category'],
            $expense['description'] ?? null,
            $expense['amount'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $userId, int $expenseId, array $fields): bool
    {
        $sets = [];
        $params = [];

        foreach ($fields as $key => $value) {
            $sets[] = $key . ' = ?';
            $params[] = $value;
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $expenseId;
        $params[] = $userId;

        return $this->pdo->prepare(
            "UPDATE expenses SET " . implode(', ', $sets) . ", updated_at = datetime('now') WHERE id = ? AND user_id = ?"
        )->execute($params);
    }

    public function delete(int $userId, int $expenseId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        return $stmt->execute([$expenseId, $userId]);
    }
}
