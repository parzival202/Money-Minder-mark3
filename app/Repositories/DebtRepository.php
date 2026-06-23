<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class DebtRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM debts WHERE user_id = ? ORDER BY status ASC, created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, string $label, float $totalAmount, string $note = ''): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO debts (user_id, label, total_amount, amount_paid, note) VALUES (?, ?, ?, 0, ?)'
        );
        $stmt->execute([$userId, $label, $totalAmount, $note]);

        return (int)$this->pdo->lastInsertId();
    }

    public function addPayment(int $userId, int $debtId, float $paymentAmount): void
    {
        $this->pdo->prepare(
            'UPDATE debts SET amount_paid = MIN(amount_paid + ?, total_amount) WHERE id = ? AND user_id = ?'
        )->execute([$paymentAmount, $debtId, $userId]);

        $this->pdo->prepare(
            "UPDATE debts SET status = 'settled' WHERE id = ? AND user_id = ? AND amount_paid >= total_amount"
        )->execute([$debtId, $userId]);
    }

    public function delete(int $userId, int $debtId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM debts WHERE id = ? AND user_id = ?');
        $stmt->execute([$debtId, $userId]);
    }
}
