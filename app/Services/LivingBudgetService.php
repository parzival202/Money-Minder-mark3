<?php

namespace App\Services;

use App\Repositories\BudgetRepository;
use App\Repositories\DebtRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\MetaRepository;

class LivingBudgetService
{
    private BudgetRepository $budgets;
    private DebtRepository $debts;
    private ExpenseRepository $expenses;
    private MetaRepository $meta;

    public function __construct()
    {
        $this->budgets = new BudgetRepository();
        $this->debts = new DebtRepository();
        $this->expenses = new ExpenseRepository();
        $this->meta = new MetaRepository();
    }

    public function calculate(int $userId): array
    {
        $budgetMap = $this->budgets->mapForUser($userId);
        $expenses = $this->expenses->allForUser($userId);
        $monthlyBudget = (float)$this->meta->getForUser('monthly_budget', $userId, '0');
        $savingsGoal = (float)($budgetMap['Épargne'] ?? 0);

        $fixedCategories = ['Alimentation', 'Transport', 'Abonnement mensuel', 'Logement', 'Facture', 'Factures', 'Santé', 'Sante', 'Remboursement'];
        $fixedExpenses = 0.0;
        foreach ($budgetMap as $category => $amount) {
            if (in_array((string)$category, $fixedCategories, true)) {
                $fixedExpenses += (float)$amount;
            }
        }

        $activeDebtBalance = 0.0;
        foreach ($this->debts->allForUser($userId) as $debt) {
            if (($debt['status'] ?? 'active') !== 'active') {
                continue;
            }
            $activeDebtBalance += max(0, (float)$debt['total_amount'] - (float)$debt['amount_paid']);
        }

        $spentThisMonth = array_sum(array_map(fn(array $expense): float => (float)$expense['amount'], $expenses));
        $remainingMonthBudget = $monthlyBudget - $spentThisMonth;

        $today = new \DateTime();
        $cycle = (new ArchiveService())->cycleBounds($today);
        $daysRemaining = max((int)$today->diff($cycle['end'])->days, 0);
        $recommendedPerDay = $daysRemaining > 0 ? max($remainingMonthBudget / $daysRemaining, 0) : max($remainingMonthBudget, 0);
        $projectedOverrun = $daysRemaining > 0
            ? max(($spentThisMonth + ($this->dailyAverage($today, $expenses) * $daysRemaining)) - $monthlyBudget, 0)
            : max($spentThisMonth - $monthlyBudget, 0);

        return [
            'monthly_income_available' => $monthlyBudget,
            'fixed_expenses' => $fixedExpenses,
            'debt_balance' => $activeDebtBalance,
            'savings_goal' => $savingsGoal,
            'remaining_month_budget' => $remainingMonthBudget,
            'days_remaining' => $daysRemaining,
            'recommended_daily_max' => $recommendedPerDay,
            'spent_this_month' => $spentThisMonth,
            'projected_overrun' => $projectedOverrun,
        ];
    }

    private function dailyAverage(\DateTime $today, array $expenses): float
    {
        $dayOfMonth = max((int)$today->format('j'), 1);
        $spent = array_sum(array_map(fn(array $expense): float => (float)$expense['amount'], $expenses));
        return $spent / $dayOfMonth;
    }
}
