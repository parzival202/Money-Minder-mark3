<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use App\Repositories\PurchaseDecisionRepository;

class PurchaseAdvisorService
{
    private CategoryRepository $categories;
    private PurchaseDecisionRepository $decisions;
    private StrictModeService $strictMode;
    private LivingBudgetService $livingBudget;
    private MoneyGuardService $moneyGuard;

    public function __construct()
    {
        $this->categories = new CategoryRepository();
        $this->decisions = new PurchaseDecisionRepository();
        $this->strictMode = new StrictModeService();
        $this->livingBudget = new LivingBudgetService();
        $this->moneyGuard = new MoneyGuardService();
    }

    public function evaluate(int $userId, array $payload, bool $persist = true): array
    {
        $amount = max((float)($payload['amount'] ?? 0), 0);
        $categoryName = trim((string)($payload['category'] ?? ''));
        $type = ($payload['type'] ?? 'need') === 'want' ? 'want' : 'need';
        $urgency = in_array(($payload['urgency'] ?? ''), ['faible', 'moyenne', 'élevée'], true)
            ? (string)$payload['urgency']
            : 'faible';
        $description = trim((string)($payload['description'] ?? ''));

        $category = $categoryName !== '' ? $this->categories->findOrCreate($userId, $categoryName) : null;
        $guard = $this->moneyGuard->evaluate($userId);
        $living = $this->livingBudget->calculate($userId);
        $strict = $this->strictMode->isEnabled($userId);
        $categorySnapshot = $guard['category_snapshot'][$categoryName] ?? null;

        $decision = 'Autorisé';
        $reason = 'Achat compatible avec ton équilibre actuel.';

        if ($strict && $type === 'want' && in_array($urgency, ['faible', 'moyenne'], true) && in_array($guard['status'], ['red', 'black'], true)) {
            $decision = 'Interdit aujourd’hui';
            $reason = 'Mode strict actif : envie non urgente pendant une journée à risque.';
        } elseif ($amount > ($living['recommended_daily_max'] ?? 0)) {
            $decision = $strict ? 'Attends 24h' : 'À éviter';
            $reason = 'Le montant dépasse ton reste à vivre journalier recommandé.';
        } elseif ($categorySnapshot && ($categorySnapshot['budget'] ?? 0) > 0 && ($categorySnapshot['spent'] ?? 0) >= ($categorySnapshot['budget'] ?? 0)) {
            $decision = 'À éviter';
            $reason = 'Cette catégorie est déjà dépassée ce mois-ci.';
        } elseif ($type === 'need' && $urgency === 'élevée' && $amount <= (($living['recommended_daily_max'] ?? 0) * 1.2)) {
            $decision = 'Autorisé';
            $reason = 'Besoin urgent avec montant encore raisonnable.';
        }

        if ($persist) {
            $this->decisions->create(
                $userId,
                $amount,
                $category ? (int)$category['id'] : null,
                $type,
                $urgency,
                $description,
                $decision,
                $reason
            );
        }

        return [
            'decision' => $decision,
            'reason' => $reason,
            'status' => $guard['status'],
            'strict_mode' => $strict,
        ];
    }
}
