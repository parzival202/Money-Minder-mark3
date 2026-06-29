<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class BudgetRepository
{
    private PDO $pdo;
    private \App\Services\BudgetService $budgetService;
    private CategoryRepository $categories;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->budgetService = new \App\Services\BudgetService();
        $this->categories = new CategoryRepository();
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT category, amount FROM budgets WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mapForUser(int $userId): array
    {
        return $this->budgetService->normalizeBudgetRows($this->allForUser($userId));
    }

    public function replaceForUser(int $userId, array $budgets): void
    {
        $budgets = $this->budgetService->normalizeBudgetMap($budgets);
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        $this->pdo->prepare('DELETE FROM budgets WHERE user_id = ?')->execute([$userId]);
        $insert = $this->pdo->prepare('INSERT INTO budgets (user_id, category, amount) VALUES (?, ?, ?)');
        foreach ($budgets as $category => $amount) {
            $insert->execute([$userId, $category, $amount]);
        }
        $this->categories->syncFromBudgetMap($userId, $budgets);

        if ($ownsTransaction) {
            $this->pdo->commit();
        }
    }
}
