<?php

namespace App\Services;

use App\Repositories\BudgetRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\MetaRepository;

class DashboardService
{
    private BudgetRepository $budgets;
    private ExpenseRepository $expenses;
    private MetaRepository $meta;

    public function __construct(
        ?BudgetRepository $budgets = null,
        ?ExpenseRepository $expenses = null,
        ?MetaRepository $meta = null
    )
    {
        $this->budgets = $budgets ?? new BudgetRepository();
        $this->expenses = $expenses ?? new ExpenseRepository();
        $this->meta = $meta ?? new MetaRepository();
    }

    public function buildCategorySnapshot(int $userId): array
    {
        $snapshot = [];
        foreach ($this->budgets->mapForUser($userId) as $category => $amount) {
            $snapshot[$category] = [
                'budget' => (float)$amount,
                'spent' => $this->expenses->totalForCategory($userId, (string)$category),
            ];
        }
        return $snapshot;
    }

    public function buildViewData(int $userId): array
    {
        $expenses = $this->expenses->allForUser($userId);
        $budgets = $this->budgets->mapForUser($userId);

        if (!isset($budgets['Épargne'])) {
            $budgets['Épargne'] = 50000.0;
        }

        $weekDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $weekExpenses = array_fill(0, 7, 0.0);
        $today = new \DateTime();
        $monday = clone $today;
        if ($today->format('N') !== '1') {
            $monday->modify('last monday');
        }

        for ($i = 0; $i < 7; $i++) {
            $date = (clone $monday)->modify("+{$i} days")->format('Y-m-d');
            foreach ($expenses as $expense) {
                if (($expense['date'] ?? '') === $date) {
                    $weekExpenses[$i] += (float)$expense['amount'];
                }
            }
        }

        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $remainingBudget = array_sum($budgets) - $totalExpenses;
        $dailyAverage = date('j') > 0 ? $totalExpenses / date('j') : 0;
        $savingsPercentage = ($remainingBudget > 0 && defined('MONTHLY_SAVING_GOAL') && MONTHLY_SAVING_GOAL > 0)
            ? ($remainingBudget / MONTHLY_SAVING_GOAL) * 100
            : 0;

        $monthlyBudget = (float)($this->meta->get('monthly_budget_user_' . $userId) ?? 0);
        $budgetUsedPercent = $monthlyBudget > 0 ? min(($totalExpenses / $monthlyBudget) * 100, 100) : 0;
        $barColor = $budgetUsedPercent < 60 ? 'bg-success' : ($budgetUsedPercent < 85 ? 'bg-warning' : 'bg-danger');

        $bannerType = null;
        $bannerIcon = null;
        $bannerMessage = null;
        if ($budgetUsedPercent >= 100) {
            $bannerType = 'danger';
            $bannerIcon = 'fa-triangle-exclamation';
            $bannerMessage = 'Budget mensuel dépassé ! Tu as dépensé ' . $this->formatCurrency($totalExpenses) . ' sur ' . $this->formatCurrency($monthlyBudget) . '.';
        } elseif ($budgetUsedPercent >= 80) {
            $bannerType = 'warning';
            $bannerIcon = 'fa-bell';
            $bannerMessage = 'Attention : ' . number_format($budgetUsedPercent, 1) . '% du budget consommé. Il te reste ' . $this->formatCurrency($monthlyBudget - $totalExpenses) . '.';
        }

        $day = (int)$today->format('d');
        $month = (int)$today->format('m');
        $year = (int)$today->format('Y');
        if ($day >= 27) {
            $cycleStart = new \DateTime("{$year}-{$month}-27");
        } else {
            $previousMonth = $month - 1;
            $previousYear = $year;
            if ($previousMonth === 0) {
                $previousMonth = 12;
                $previousYear--;
            }
            $cycleStart = new \DateTime("{$previousYear}-{$previousMonth}-27");
        }
        $cycleEnd = (clone $cycleStart)->modify('+30 days');
        $daysElapsed = (int)$cycleStart->diff($today)->days + 1;
        $daysTotal = 30;
        $daysRemaining = max($daysTotal - $daysElapsed, 0);

        $categorySpending = [];
        foreach ($budgets as $category => $budgetAmount) {
            $budgetAmount = (float)$budgetAmount;
            if ($budgetAmount <= 0) {
                continue;
            }
            $spent = $this->expenses->totalForCategory($userId, $category);
            $categorySpending[$category] = [
                'spent' => $spent,
                'budget' => $budgetAmount,
                'percent' => round(($spent / $budgetAmount) * 100, 1),
            ];
        }
        uasort($categorySpending, fn(array $a, array $b) => $b['spent'] <=> $a['spent']);

        $diverseColors = ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#8AC926','#1982C4','#F472B6','#60A5FA','#34D399','#FBBF24'];
        $chartColors = [];
        $colorIndex = 0;
        foreach (array_keys($budgets) as $category) {
            if ($category === 'Épargne') {
                $chartColors[] = '#DC3545';
            } elseif ($category === 'Alimentation') {
                $chartColors[] = '#1E40AF';
            } else {
                $chartColors[] = $diverseColors[$colorIndex % count($diverseColors)];
                $colorIndex++;
            }
        }

        $expenseChartLabels = array_values(array_keys($budgets));
        $expenseChartData = array_map(
            fn(string $category) => $this->expenses->totalForCategory($userId, $category),
            $expenseChartLabels
        );

        $budgetChartLabels = [];
        $budgetChartData = [];
        $spentChartData = [];
        foreach ($budgets as $category => $budgetAmount) {
            if ((float)$budgetAmount <= 0) {
                continue;
            }
            $budgetChartLabels[] = $category;
            $budgetChartData[] = (float)$budgetAmount;
            $spentChartData[] = $this->expenses->totalForCategory($userId, $category);
        }

        return [
            'expenses' => $expenses,
            'budgets' => $budgets,
            'weekDays' => $weekDays,
            'weekExpenses' => $weekExpenses,
            'total_expenses' => $totalExpenses,
            'remaining_budget' => $remainingBudget,
            'daily_average' => $dailyAverage,
            'savings_percentage' => $savingsPercentage,
            'monthly_budget' => $monthlyBudget,
            'budget_used_percent' => $budgetUsedPercent,
            'bar_color' => $barColor,
            'banner_type' => $bannerType,
            'banner_icon' => $bannerIcon,
            'banner_message' => $bannerMessage,
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
            'days_elapsed' => $daysElapsed,
            'days_total' => $daysTotal,
            'days_remaining' => $daysRemaining,
            'top3' => array_slice($categorySpending, 0, 3, true),
            'chartColors' => $chartColors,
            'expenseChartLabels' => $expenseChartLabels,
            'expenseChartData' => $expenseChartData,
            'budgetChartLabels' => $budgetChartLabels,
            'budgetChartData' => $budgetChartData,
            'spentChartData' => $spentChartData,
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}
