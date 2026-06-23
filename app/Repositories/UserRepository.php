<?php

namespace App\Repositories;

use App\Support\Database;
use PDO;

class UserRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, first_name, last_name, is_admin, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT id, username, first_name, last_name, is_admin, created_at FROM users ORDER BY created_at ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $username, string $passwordHash, bool $isAdmin, string $firstName, string $lastName): int|false
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, first_name, last_name, password_hash, is_admin) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                trim($username),
                trim($firstName),
                trim($lastName),
                $passwordHash,
                $isAdmin ? 1 : 0,
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function update(int $userId, array $fields): bool
    {
        $allowed = ['username', 'first_name', 'last_name', 'password_hash', 'is_admin'];
        $sets = [];
        $params = [];

        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            $sets[] = $key . ' = ?';
            $params[] = $value;
        }

        if ($sets === []) {
            return false;
        }

        $params[] = $userId;

        try {
            return $this->pdo->prepare(
                'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?'
            )->execute($params);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function delete(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function countAdmins(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1');
        return (int)$stmt->fetchColumn();
    }
}
