<?php

namespace App\Services;

use DateTime;

class ApiService
{
    public function budgetVsSpent(int $userId): array
    {
        $budgets = getBudgets($userId);
        $categories = [];
        $totalBudget = 0.0;
        $totalSpent = 0.0;

        foreach ($budgets as $category => $amount) {
            $budgetAmount = (float)$amount;
            $spentAmount = calculateCategoryExpenses($category, $userId);

            $categories[$category] = [
                'budget' => $budgetAmount,
                'spent' => $spentAmount,
                'remaining' => $budgetAmount - $spentAmount,
            ];

            $totalBudget += $budgetAmount;
            $totalSpent += $spentAmount;
        }

        return [
            'success' => true,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'categories' => $categories,
        ];
    }

    public function categoryDistribution(int $userId): array
    {
        $budgets = getBudgets($userId);
        $totalBudget = array_sum($budgets);
        $distribution = [];

        foreach ($budgets as $category => $amount) {
            $amount = (float)$amount;
            $distribution[] = [
                'category' => $category,
                'amount' => $amount,
                'percentage' => $totalBudget > 0 ? round(($amount / $totalBudget) * 100, 2) : 0,
            ];
        }

        return [
            'success' => true,
            'total_budget' => (float)$totalBudget,
            'distribution' => $distribution,
        ];
    }

    public function expensesEvolution(int $userId): array
    {
        $expenses = fetchExpenses($userId);
        $evolution = [];

        foreach ($expenses as $expense) {
            $date = $expense['date'];
            if (!isset($evolution[$date])) {
                $evolution[$date] = 0.0;
            }
            $evolution[$date] += (float)$expense['amount'];
        }

        ksort($evolution);

        $points = [];
        foreach ($evolution as $date => $amount) {
            $points[] = [
                'date' => $date,
                'amount' => $amount,
            ];
        }

        return [
            'success' => true,
            'points' => $points,
        ];
    }

    public function weekExpenses(int $userId): array
    {
        $expenses = fetchExpenses($userId);
        $today = new DateTime('today');
        $start = (clone $today)->modify('-6 days');
        $days = [];

        for ($cursor = clone $start; $cursor <= $today; $cursor->modify('+1 day')) {
            $days[$cursor->format('Y-m-d')] = 0.0;
        }

        foreach ($expenses as $expense) {
            $date = $expense['date'];
            if (isset($days[$date])) {
                $days[$date] += (float)$expense['amount'];
            }
        }

        $series = [];
        foreach ($days as $date => $amount) {
            $series[] = [
                'date' => $date,
                'amount' => $amount,
            ];
        }

        return [
            'success' => true,
            'series' => $series,
        ];
    }

    public function checkAlerts(int $userId): array
    {
        require_once dirname(__DIR__, 2) . '/telegram_bot.php';

        global $__nikolaii;
        if (!isset($__nikolaii)) {
            $__nikolaii = new \Nikolaii();
        }

        checkAndSendAlerts($userId);
        return fetchAlerts($userId);
    }

    public function archiveCurrentMonth(int $userId): array
    {
        require_once dirname(__DIR__, 2) . '/telegram_bot.php';

        global $__nikolaii;
        if (!isset($__nikolaii)) {
            $__nikolaii = new \Nikolaii();
        }

        $archiveResult = archiveCurrentCycle($userId);
        if ($archiveResult['success']) {
            $__nikolaii->sendMessage(buildArchiveSummaryMessage($archiveResult), $userId);

            return [
                'success' => true,
                'message' => $archiveResult['message'],
                'cycle' => $archiveResult['cycle'],
            ];
        }

        return [
            'success' => false,
            'status' => $archiveResult['status'] ?? 'error',
            'message' => $archiveResult['message'] ?? 'Échec de l’archivage.',
            'cycle' => $archiveResult['cycle'] ?? null,
        ];
    }

    public function triggerTestAlert(int $userId): array
    {
        $alertId = insertAlert($userId, 'test', 'This is a test alert to verify the red dot badge functionality.');

        if ($alertId) {
            return [
                'success' => true,
                'message' => 'Test alert created successfully',
                'alert_id' => $alertId,
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create test alert',
        ];
    }
}
