<?php

namespace App\Services;

class SetupService
{
    public function buildPageData(int $userId, array $post = []): array
    {
        $currentUser = fetchUserById($userId);
        ensureUserBudgetMetaConsistency($userId);

        $template = getBudgetTemplateRatios();
        $sourceBudgets = $template['source_budgets'];
        $sourceMonthlyBudget = array_sum($sourceBudgets);
        $sourceSavings = (float)($sourceBudgets['Épargne'] ?? 0);
        $defaultMonthlyBudget = $sourceMonthlyBudget > 0 ? $sourceMonthlyBudget : 200000;
        $defaultSavings = $sourceSavings > 0 ? $sourceSavings : round($defaultMonthlyBudget * 0.25);

        $submittedMonthlyBudget = isset($post['monthly_budget'])
            ? max((float)$post['monthly_budget'], 0)
            : $defaultMonthlyBudget;
        $submittedSavings = isset($post['saving_amount'])
            ? max((float)$post['saving_amount'], 0)
            : $defaultSavings;

        $suggestedBudgets = suggestBudgetsFromMonthlyTarget($submittedMonthlyBudget, $submittedSavings);
        $budgetInputs = $suggestedBudgets;

        if (!empty($post['budgets']) && is_array($post['budgets'])) {
            foreach ($post['budgets'] as $category => $amount) {
                $budgetInputs[$category] = max((float)$amount, 0);
            }
        }

        return [
            'current_user' => $currentUser,
            'current_name' => getUserDisplayName($currentUser),
            'template' => $template,
            'source_budgets' => $sourceBudgets,
            'source_monthly_budget' => $sourceMonthlyBudget,
            'source_savings' => $sourceSavings,
            'submitted_monthly_budget' => $submittedMonthlyBudget,
            'submitted_savings' => $submittedSavings,
            'budget_inputs' => $budgetInputs,
            'category_ratios_json' => json_encode($template['category_ratios'], JSON_UNESCAPED_UNICODE),
        ];
    }

    public function handleSubmission(int $userId, array $post): array
    {
        $monthlyBudget = max((float)($post['monthly_budget'] ?? 0), 0);
        $savingAmount = max((float)($post['saving_amount'] ?? 0), 0);

        if ($monthlyBudget <= 0) {
            return [
                'success' => false,
                'error' => 'Veuillez définir un budget mensuel supérieur à 0.',
            ];
        }

        if ($savingAmount > $monthlyBudget) {
            return [
                'success' => false,
                'error' => 'L’épargne ne peut pas dépasser le budget mensuel.',
            ];
        }

        applyOptimalBudgets($userId, $monthlyBudget, $savingAmount, false);
        setMeta('monthly_budget', $monthlyBudget, $userId);

        return [
            'success' => true,
            'redirect' => 'index.php?budgets_updated=1',
        ];
    }
}
