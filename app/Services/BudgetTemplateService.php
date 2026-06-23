<?php

namespace App\Services;

use App\Repositories\MetaRepository;
use App\Repositories\UserRepository;

class BudgetTemplateService
{
    private UserRepository $users;
    private MetaRepository $meta;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->meta = new MetaRepository();
    }

    public function sourceUserId(): int
    {
        $user = $this->users->findByUsername('localuser');
        if ($user && !empty($user['id'])) {
            return (int)$user['id'];
        }

        return ensure_default_user();
    }

    public function ratios(): array
    {
        $sourceUserId = $this->sourceUserId();
        $budgets = getBudgets($sourceUserId);

        if (empty($budgets)) {
            $budgets = [
                'Alimentation' => 50000,
                'Transport' => 30000,
                'Loisirs/Sortie' => 20000,
                'Mode' => 15000,
                'Aide proche' => 10000,
                'Abonnement mensuel' => 25000,
                'Épargne' => 50000,
            ];
        }

        $savings = max((float)($budgets['Épargne'] ?? 0), 0);
        $nonSavings = $budgets;
        unset($nonSavings['Épargne']);

        $baseTotal = array_sum($nonSavings);
        if ($baseTotal <= 0) {
            $baseTotal = 1;
        }

        $categoryRatios = [];
        foreach ($nonSavings as $category => $amount) {
            $categoryRatios[$category] = max((float)$amount, 0) / $baseTotal;
        }

        return [
            'source_user_id' => $sourceUserId,
            'source_budgets' => $budgets,
            'savings_ratio' => ($baseTotal + $savings) > 0 ? ($savings / ($baseTotal + $savings)) : 0,
            'category_ratios' => $categoryRatios,
        ];
    }

    public function suggestFromMonthlyTarget(float $monthlyBudget, float $savingsAmount): array
    {
        $template = $this->ratios();
        $allocatable = max($monthlyBudget - $savingsAmount, 0);
        $budgets = [];
        $runningTotal = 0;
        $categoryRatios = $template['category_ratios'];
        $categories = array_keys($categoryRatios);
        $lastCategory = end($categories);

        foreach ($categoryRatios as $category => $ratio) {
            $amount = ($category === $lastCategory)
                ? max($allocatable - $runningTotal, 0)
                : round($allocatable * $ratio);
            $budgets[$category] = (float)$amount;
            $runningTotal += $amount;
        }

        $budgets['Épargne'] = max($savingsAmount, 0);
        return $budgets;
    }

    public function userNeedsSetup(int $userId): bool
    {
        $monthlyBudget = (float)$this->meta->getForUser('monthly_budget', $userId, '0');
        $budgets = getBudgets($userId);
        return $monthlyBudget <= 0 || empty($budgets);
    }

    public function ensureUserBudgetMetaConsistency(int $userId): void
    {
        $monthlyBudget = (float)$this->meta->getForUser('monthly_budget', $userId, '0');
        if ($monthlyBudget > 0) {
            return;
        }

        $budgets = getBudgets($userId);
        if (!empty($budgets)) {
            $this->meta->setForUser('monthly_budget', (string)array_sum($budgets), $userId);
        }
    }
}
