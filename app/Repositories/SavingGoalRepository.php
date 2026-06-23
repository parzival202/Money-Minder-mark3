<?php

namespace App\Repositories;

class SavingGoalRepository
{
    private MetaRepository $meta;

    public function __construct(?MetaRepository $meta = null)
    {
        $this->meta = $meta ?? new MetaRepository();
    }

    public function getForUser(int $userId): array
    {
        $value = $this->meta->get($this->key($userId));
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function saveForUser(int $userId, array $goals): void
    {
        $this->meta->set($this->key($userId), json_encode($goals, JSON_UNESCAPED_UNICODE));
    }

    private function key(int $userId): string
    {
        return 'saving_goals_' . $userId;
    }
}
