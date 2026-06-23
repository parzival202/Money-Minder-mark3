<?php

namespace App\Services;

class BudgetService
{
    private const SAVINGS_KEY = 'Épargne';

    private const SAVINGS_ALIASES = [
        'Épargne',
        'Ã‰pargne',
        'Ãƒâ€°pargne',
        '?pargne',
        '??pargne',
        '?????pargne',
    ];

    public function normalizeBudgetMap(array $budgets): array
    {
        $normalized = [];
        $savingsTotal = 0.0;

        foreach ($budgets as $category => $amount) {
            $amount = (float)$amount;
            if (in_array((string)$category, self::SAVINGS_ALIASES, true)) {
                $savingsTotal += $amount;
                continue;
            }

            $normalized[(string)$category] = $amount;
        }

        if ($savingsTotal > 0 || array_key_exists(self::SAVINGS_KEY, $budgets)) {
            $normalized[self::SAVINGS_KEY] = $savingsTotal > 0
                ? $savingsTotal
                : (float)($budgets[self::SAVINGS_KEY] ?? 0);
        }

        return $normalized;
    }

    public function normalizeBudgetRows(array $rows): array
    {
        $budgetMap = [];
        foreach ($rows as $row) {
            $budgetMap[$row['category']] = (float)$row['amount'];
        }

        return $this->normalizeBudgetMap($budgetMap);
    }

    public function getSavingsKey(): string
    {
        return self::SAVINGS_KEY;
    }
}
