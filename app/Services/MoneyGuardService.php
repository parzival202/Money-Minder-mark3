<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use App\Repositories\ExpenseRepository;

class MoneyGuardService
{
    private LivingBudgetService $livingBudget;
    private DashboardService $dashboard;
    private CategoryRepository $categories;
    private ExpenseRepository $expenses;

    public function __construct()
    {
        $this->livingBudget = new LivingBudgetService();
        $this->dashboard = new DashboardService();
        $this->categories = new CategoryRepository();
        $this->expenses = new ExpenseRepository();
    }

    public function evaluate(int $userId): array
    {
        $living = $this->livingBudget->calculate($userId);
        $todayTotal = $this->todaySpending($userId);
        $dailyLimit = max((float)$living['recommended_daily_max'], 0);
        $monthlyCritical = ($living['projected_overrun'] ?? 0) > 0 || ($living['remaining_month_budget'] ?? 0) < 0;
        $ratio = $dailyLimit > 0 ? ($todayTotal / $dailyLimit) : 0;

        if ($monthlyCritical || ($dailyLimit > 0 && $todayTotal > $dailyLimit)) {
            $status = 'black';
        } elseif ($ratio >= 0.80) {
            $status = 'red';
        } elseif ($ratio >= 0.50) {
            $status = 'orange';
        } else {
            $status = 'green';
        }

        $statusMap = [
            'green' => ['label' => 'Vert', 'color' => '#16a34a', 'message' => 'Tu gardes le contrôle aujourd’hui.'],
            'orange' => ['label' => 'Orange', 'color' => '#d97706', 'message' => 'Tu approches de ta limite journalière.'],
            'red' => ['label' => 'Rouge', 'color' => '#dc2626', 'message' => 'Alerte forte : toute dépense non essentielle est risquée.'],
            'black' => ['label' => 'Noir', 'color' => '#111827', 'message' => 'Stop dépenses aujourd’hui. Tu es hors zone de sécurité.'],
        ];

        $dangerCategory = $this->mostDangerousCategory($userId);

        return [
            'status' => $status,
            'label' => $statusMap[$status]['label'],
            'color' => $statusMap[$status]['color'],
            'message' => $statusMap[$status]['message'],
            'daily_limit' => $dailyLimit,
            'today_spent' => $todayTotal,
            'remaining_today' => max($dailyLimit - $todayTotal, 0),
            'monthly_critical' => $monthlyCritical,
            'danger_category' => $dangerCategory,
            'category_snapshot' => $this->dashboard->buildCategorySnapshot($userId),
        ];
    }

    public function requiresJustification(int $userId, string $category, float $amount): array
    {
        $guard = $this->evaluate($userId);
        $strict = (new StrictModeService())->isEnabled($userId);
        $categoryMeta = $this->categories->findByName($userId, $category);
        $isEssential = $categoryMeta ? (int)$categoryMeta['essential'] === 1 : $this->categories->guessEssential($category);

        $needsJustification = $strict && (
            in_array($guard['status'], ['red', 'black'], true)
            || !$isEssential
            || $amount > ($guard['remaining_today'] ?? 0)
        );

        return [
            'required' => $needsJustification,
            'strict_mode' => $strict,
            'status' => $guard['status'],
            'is_essential' => $isEssential,
            'message' => 'Cette dépense risque de déséquilibrer ton mois. Pourquoi veux-tu vraiment la faire ?',
        ];
    }

    private function todaySpending(int $userId): float
    {
        $today = date('Y-m-d');
        $total = 0.0;
        foreach ($this->expenses->allForUser($userId) as $expense) {
            if (($expense['date'] ?? '') === $today) {
                $total += (float)$expense['amount'];
            }
        }
        return $total;
    }

    private function mostDangerousCategory(int $userId): ?array
    {
        $snapshot = $this->dashboard->buildCategorySnapshot($userId);
        $danger = null;

        foreach ($snapshot as $category => $values) {
            $budget = (float)($values['budget'] ?? 0);
            if ($budget <= 0) {
                continue;
            }

            $percent = ((float)($values['spent'] ?? 0) / $budget) * 100;
            if ($danger === null || $percent > $danger['percent']) {
                $danger = [
                    'category' => $category,
                    'percent' => round($percent, 1),
                    'spent' => (float)$values['spent'],
                    'budget' => $budget,
                ];
            }
        }

        return $danger;
    }
}
