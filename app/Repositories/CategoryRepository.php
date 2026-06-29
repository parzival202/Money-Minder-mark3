<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class CategoryRepository
{
    private PDO $pdo;

    private const ESSENTIAL_DEFAULTS = [
        'alimentation',
        'transport',
        'sante',
        'santé',
        'logement',
        'facture',
        'factures',
        'dette',
        'remboursement',
        'abonnement mensuel',
    ];

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE user_id = ? ORDER BY name COLLATE NOCASE ASC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByName(int $userId, string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM categories WHERE user_id = ? AND name = ? LIMIT 1');
        $stmt->execute([$userId, trim($name)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOrCreate(int $userId, string $name, ?bool $essential = null): array
    {
        $existing = $this->findByName($userId, $name);
        if ($existing) {
            if ($essential !== null && (int)$existing['essential'] !== ($essential ? 1 : 0)) {
                $this->setEssential((int)$existing['id'], $userId, $essential);
                $existing['essential'] = $essential ? 1 : 0;
            }
            return $existing;
        }

        $isEssential = $essential ?? $this->guessEssential($name);
        $stmt = $this->pdo->prepare('INSERT INTO categories (user_id, name, essential) VALUES (?, ?, ?)');
        $stmt->execute([$userId, trim($name), $isEssential ? 1 : 0]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'user_id' => $userId,
            'name' => trim($name),
            'essential' => $isEssential ? 1 : 0,
        ];
    }

    public function syncFromBudgetMap(int $userId, array $budgets): void
    {
        foreach (array_keys($budgets) as $name) {
            if ((string)$name === '') {
                continue;
            }
            $this->findOrCreate($userId, (string)$name);
        }
    }

    public function setEssential(int $categoryId, int $userId, bool $essential): bool
    {
        $stmt = $this->pdo->prepare('UPDATE categories SET essential = ? WHERE id = ? AND user_id = ?');
        return $stmt->execute([$essential ? 1 : 0, $categoryId, $userId]);
    }

    public function guessEssential(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        return in_array($normalized, self::ESSENTIAL_DEFAULTS, true);
    }
}
