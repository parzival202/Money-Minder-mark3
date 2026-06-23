<?php

namespace App\Services;

use App\Repositories\SavingGoalRepository;

class SavingGoalService
{
    private SavingGoalRepository $repository;

    public function __construct(?SavingGoalRepository $repository = null)
    {
        $this->repository = $repository ?? new SavingGoalRepository();
    }

    public function allForUser(int $userId): array
    {
        return $this->repository->getForUser($userId);
    }

    public function saveForUser(int $userId, array $goals): void
    {
        $this->repository->saveForUser($userId, $goals);
    }
}
