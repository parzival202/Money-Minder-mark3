<?php

namespace App\Services;

use App\Repositories\ArchiveRepository;
use App\Repositories\ExpenseRepository;
use App\Support\Database;
use DateTime;
use Throwable;

class ArchiveService
{
    private ArchiveRepository $archives;
    private ExpenseRepository $expenses;

    public function __construct()
    {
        $this->archives = new ArchiveRepository();
        $this->expenses = new ExpenseRepository();
    }

    public function cycleBounds(DateTime|string $referenceDate = 'now'): array
    {
        $dt = $referenceDate instanceof DateTime ? clone $referenceDate : new DateTime((string)$referenceDate);
        $day = (int)$dt->format('d');

        if ($day >= 27) {
            $start = new DateTime($dt->format('Y-m-27'));
        } else {
            $prev = new DateTime($dt->format('Y-m-01'));
            $prev->modify('-1 month');
            $start = new DateTime($prev->format('Y-m-27'));
        }

        $end = (clone $start)->modify('+30 days');

        return [
            'start' => $start,
            'end' => $end,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'month_year' => $start->format('Y-m'),
            'legacy_month_year' => $end->format('Y-m'),
            'display_label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        ];
    }

    public function findForCycle(int $userId, array $cycle): ?array
    {
        return $this->archives->findForCycle($userId, $cycle['month_year'], $cycle['legacy_month_year']);
    }

    public function archiveCurrentCycle(int $userId, DateTime|string $referenceDate = 'now'): array
    {
        $pdo = Database::connection();
        $cycle = $this->cycleBounds($referenceDate);
        $existingArchive = $this->findForCycle($userId, $cycle);

        if ($existingArchive) {
            return [
                'success' => false,
                'status' => 'already_archived',
                'message' => 'Cette période est déjà archivée.',
                'cycle' => $cycle,
                'archive' => $existingArchive,
            ];
        }

        $expenses = $this->expenses->allForUser($userId);
        $expenses = array_values(array_filter($expenses, function (array $expense) use ($cycle): bool {
            $date = (string)($expense['date'] ?? '');
            return $date >= $cycle['start_date'] && $date <= $cycle['end_date'];
        }));

        if ($expenses === []) {
            return [
                'success' => false,
                'status' => 'no_expenses',
                'message' => 'Aucune dépense à archiver pour cette période.',
                'cycle' => $cycle,
            ];
        }

        $budgets = getBudgets($userId);
        $monthlyBudget = (float)getMeta('monthly_budget', (string)array_sum($budgets), $userId);
        $totalExpenses = array_reduce($expenses, fn (float $sum, array $expense): float => $sum + (float)$expense['amount'], 0.0);

        $archiveData = [
            'period_start' => $cycle['start_date'],
            'period_end' => $cycle['end_date'],
            'display_label' => $cycle['display_label'],
            'monthly_budget' => $monthlyBudget,
            'budgets' => $budgets,
            'expenses' => $expenses,
        ];

        $resetBudgets = $budgets;
        foreach ($resetBudgets as $category => &$amount) {
            if ($category !== 'Épargne') {
                $amount = 0;
            }
        }
        unset($amount);

        try {
            $pdo->beginTransaction();
            $this->archives->save($userId, $cycle['month_year'], $archiveData, $totalExpenses);
            setBudgets($userId, $resetBudgets);
            $deleteStmt = $pdo->prepare('DELETE FROM expenses WHERE user_id = ? AND date >= ? AND date <= ?');
            $deleteStmt->execute([$userId, $cycle['start_date'], $cycle['end_date']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Échec de l’archivage: ' . $exception->getMessage(),
                'cycle' => $cycle,
            ];
        }

        return [
            'success' => true,
            'status' => 'archived',
            'message' => 'Période archivée avec succès.',
            'cycle' => $cycle,
            'total_expenses' => $totalExpenses,
            'savings_amount' => (float)($budgets['Épargne'] ?? 0),
            'monthly_budget' => $monthlyBudget,
            'budgets' => $budgets,
            'expenses' => $expenses,
        ];
    }

    public function buildSummaryMessage(array $archiveResult): string
    {
        $cycle = $archiveResult['cycle'];
        $total = formatCurrency($archiveResult['total_expenses'] ?? 0);
        $savings = formatCurrency($archiveResult['savings_amount'] ?? 0);

        return "Mois archivé ! {$cycle['display_label']} : {$total} dépensés, {$savings} épargnés.";
    }

    public function previousMonthSavings(int $userId): float
    {
        $reference = new DateTime('first day of last month');
        $archive = $this->findForCycle($userId, $this->cycleBounds($reference));

        if ($archive) {
            $data = $this->decodeArchiveData($archive);
            if (isset($data['budgets']['Épargne'])) {
                return (float)$data['budgets']['Épargne'];
            }
        }

        return 0.0;
    }

    public function decodeArchiveData(array $archive): array
    {
        if (empty($archive['data_json'])) {
            return [];
        }

        $data = json_decode($archive['data_json'], true);
        return is_array($data) ? $data : [];
    }
}
