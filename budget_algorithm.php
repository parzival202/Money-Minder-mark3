<?php
// ============================================================
// budget_algorithm.php
// Algorithme de répartition budgétaire 50/30/20
// À inclure dans db.php ou require_once depuis setup.php / index.php
// ============================================================

/**
 * Calcule la répartition optimale des budgets selon la règle 50/30/20,
 * en tenant compte des dettes actives de l'utilisateur.
 *
 * @param float $monthly_budget   Budget mensuel brut saisi par l'user
 * @param float $savings_goal     Montant d'épargne souhaité (ou 0 pour appliquer les 20% auto)
 * @param array $active_debts     Tableau des dettes actives (depuis fetchDebts())
 * @param array $existing_cats    Catégories existantes de l'user (pour garder les customs)
 *
 * @return array [
 *   'budgets'          => [...],   // tableau catégorie => montant
 *   'breakdown'        => [...],   // détail lisible pour affichage
 *   'monthly_budget'   => float,   // budget brut
 *   'savings'          => float,   // montant épargne
 *   'debt_provision'   => float,   // provision dettes
 *   'distributable'    => float,   // budget réellement distribué
 * ]
 */
function calculateOptimalBudgets(
    float $monthly_budget,
    float $savings_goal = 0,
    array $active_debts = [],
    array $existing_cats = []
): array {

    // ── Catégories de base avec leur groupe et leur poids relatif ──
    // Groupe "needs" = 50% du budget distribuable
    // Groupe "wants" = 30% du budget distribuable
    // Les poids sont relatifs AU SEIN de leur groupe (somme = 100 par groupe)
    $default_categories = [
        // Besoins essentiels — 50%
        'Alimentation'       => ['group' => 'needs', 'weight' => 50],  // 25% du total
        'Transport'          => ['group' => 'needs', 'weight' => 30],  // 15% du total
        'Abonnement mensuel' => ['group' => 'needs', 'weight' => 20],  // 10% du total

        // Envies / Loisirs — 30%
        'Loisirs/Sortie'     => ['group' => 'wants', 'weight' => 50],  // 15% du total
        'Mode'               => ['group' => 'wants', 'weight' => 33],  // ~10% du total
        'Aide proche'        => ['group' => 'wants', 'weight' => 17],  //  ~5% du total
    ];

    // Si l'user a des catégories custom (non présentes dans les defaults),
    // on les ajoute dans le groupe "wants" avec un poids par défaut
    foreach ($existing_cats as $cat => $amount) {
        if ($cat === 'Épargne' || $cat === 'Remboursement') continue;
        if (!isset($default_categories[$cat])) {
            $default_categories[$cat] = ['group' => 'wants', 'weight' => 20];
        }
    }

    // ── Étape 1 : Calculer l'épargne ──────────────────────────────
    if ($savings_goal > 0) {
        // L'user a fixé un objectif d'épargne précis
        $savings = min($savings_goal, $monthly_budget * 0.40); // plafond à 40%
    } else {
        // On applique les 20% automatiquement
        $savings = round($monthly_budget * 0.20);
    }

    // ── Étape 2 : Calculer la provision pour les dettes ───────────
    $debt_provision = 0;
    if (!empty($active_debts)) {
        $total_debt_remaining = 0;
        foreach ($active_debts as $debt) {
            if (($debt['status'] ?? 'active') !== 'active') continue;
            $remaining = floatval($debt['total_amount']) - floatval($debt['amount_paid']);
            if ($remaining > 0) $total_debt_remaining += $remaining;
        }

        if ($total_debt_remaining > 0) {
            // Mensualité estimée sur 6 mois
            $estimated_monthly = $total_debt_remaining / 6;
            // Plafond : 15% du budget mensuel (pour ne pas écraser les autres catégories)
            $max_debt_provision = $monthly_budget * 0.15;
            $debt_provision = round(min($estimated_monthly, $max_debt_provision));
        }
    }

    // ── Étape 3 : Budget réellement distribuable ──────────────────
    $distributable = $monthly_budget - $savings - $debt_provision;
    $distributable = max($distributable, 0);

    // ── Étape 4 : Répartir selon 50/30/20 ────────────────────────
    $needs_budget = $distributable * 0.50; // 50% pour les besoins
    $wants_budget = $distributable * 0.30; // 30% pour les envies
    // Note : les 20% restants sont "tampon" / marge de sécurité non attribués

    // Calculer les poids totaux par groupe (pour normaliser si catégories customs)
    $total_needs_weight = 0;
    $total_wants_weight = 0;
    foreach ($default_categories as $cat => $cfg) {
        if ($cfg['group'] === 'needs') $total_needs_weight += $cfg['weight'];
        if ($cfg['group'] === 'wants') $total_wants_weight += $cfg['weight'];
    }

    // ── Étape 5 : Construire le tableau final ─────────────────────
    $budgets = [];
    $breakdown = [];

    // Épargne en premier
    $budgets['Épargne'] = round($savings);
    $breakdown['Épargne'] = [
        'amount'  => round($savings),
        'percent' => $monthly_budget > 0 ? round(($savings / $monthly_budget) * 100, 1) : 0,
        'group'   => 'savings',
        'label'   => 'Épargne (20%)',
    ];

    // Provision dettes (catégorie Remboursement si dettes actives)
    if ($debt_provision > 0) {
        $budgets['Remboursement'] = round($debt_provision);
        $breakdown['Remboursement'] = [
            'amount'  => round($debt_provision),
            'percent' => $monthly_budget > 0 ? round(($debt_provision / $monthly_budget) * 100, 1) : 0,
            'group'   => 'debts',
            'label'   => 'Provision dettes',
        ];
    }

    // Catégories besoins et envies
    foreach ($default_categories as $cat => $cfg) {
        if ($cfg['group'] === 'needs') {
            $amount = ($cfg['weight'] / $total_needs_weight) * $needs_budget;
        } else {
            $amount = ($cfg['weight'] / $total_wants_weight) * $wants_budget;
        }

        $amount = round($amount / 100) * 100; // Arrondi au 100 FCFA le plus proche
        $amount = max($amount, 0);

        $budgets[$cat] = $amount;
        $breakdown[$cat] = [
            'amount'  => $amount,
            'percent' => $monthly_budget > 0 ? round(($amount / $monthly_budget) * 100, 1) : 0,
            'group'   => $cfg['group'],
            'label'   => $cfg['group'] === 'needs' ? 'Besoin essentiel' : 'Envie / Loisir',
        ];
    }

    return [
        'budgets'        => $budgets,
        'breakdown'      => $breakdown,
        'monthly_budget' => $monthly_budget,
        'savings'        => $savings,
        'debt_provision' => $debt_provision,
        'distributable'  => $distributable,
    ];
}

/**
 * Applique les budgets calculés en base de données pour un utilisateur.
 * Préserve les catégories custom existantes non présentes dans l'algo.
 *
 * @param int   $user_id
 * @param float $monthly_budget
 * @param float $savings_goal
 * @param bool  $preserve_custom  Si true, garde les catégories custom de l'user
 */
function applyOptimalBudgets(
    int $user_id,
    float $monthly_budget,
    float $savings_goal = 0,
    bool $preserve_custom = true
): array {
    // Récupère les dettes actives
    $all_debts    = fetchDebts($user_id);
    $active_debts = array_filter($all_debts, fn($d) => ($d['status'] ?? 'active') === 'active');

    // Récupère les catégories existantes (pour préserver les customs)
    $existing_cats = $preserve_custom ? getBudgets($user_id) : [];

    // Calcule la répartition optimale
    $result = calculateOptimalBudgets($monthly_budget, $savings_goal, $active_debts, $existing_cats);

    // Si preserve_custom, fusionne : les customs gardent leur montant actuel
    // mais les catégories de base sont recalculées
    if ($preserve_custom && !empty($existing_cats)) {
        $default_cat_names = [
            'Alimentation', 'Transport', 'Abonnement mensuel',
            'Loisirs/Sortie', 'Mode', 'Aide proche', 'Épargne', 'Remboursement'
        ];
        foreach ($existing_cats as $cat => $amount) {
            if (!in_array($cat, $default_cat_names) && !isset($result['budgets'][$cat])) {
                // Catégorie custom → on la garde avec son montant actuel
                $result['budgets'][$cat] = $amount;
            }
        }
    }

    // Sauvegarde en DB
    setBudgets($user_id, $result['budgets']);
    setMeta('monthly_budget', $monthly_budget, $user_id);

    return $result;
}
?>
