<?php
// =============================
// Bot Telegram – Nikolaii
// =============================

// ⚠️ Garder ces constantes telles quelles (fournies par l'utilisateur)
define('BOT_TOKEN', '8053196328:AAFgAAMgHUNrFyBBjm8S_98QC5eWv91p-DA');
define('CHAT_ID', '922057959');
define('CURRENCY', 'FCFA');

class Nikolaii {
    private array $messages = [
        'large_expense' => [
            "😳 Tu es sérieux ?{amount} pour {category} ?! \nTu as perdu la tête ?",
            "{amount} pour {category} ?! 🤯 \n\nOn dirait que l'argent pousse dans ton jardin hein!",
            "Mais... \n{amount} pour {category} ?! 💸 Tu voulais acheter quoi ? l'Afrique de l'Ouest ?",
            "Nikolaii à l'appareil. Code rouge : \n{amount} dépensés en {category}. 🚨 On fait un bilan ?",
            "Aaah Doug Saga ? \nDépenser {amount} dans {category} seulement ? Ce mois là va t'enseigner la vie 😂"
        ],
        'budget_warning' => [
            "Attention chef ! \n {category} est à {percentage}% du budget. \n🚧 Faut calmer le jeu !",
            "Psst... {category} : {percentage}% dépensés. \n🫣 Tu veux finir le mois avec des pâtes ?",
            "{category} : {percentage}% du budget.\n😬 On respire un coup et on réfléchit ?",
            "Rapport budget : {category} à {percentage}%. ⚠️ C'est le moment de se poser les bonnes questions.",
            "Toi tu n'as pas dit tu dépenses on dirait tu sais pas compter ?! \n\n{category} est à {percentage}% ooh.\n\n⚠️ ARRÊTE DE TE JOUER LES BÊTES !!"
        ],
        'budget_exceeded' => [
            "C'est officiel : budget {category} explosé ! 💥 {percentage}% dépensés. \nComment on fait maintenant ?",
            "ALERTE ROUGE ! Budget {category} dépassé de {percentage}%. \n🚨 On repart sur de nouvelles bases ?",
            "De la manière le budget {category} est dépassé de {percentage}% là… j'espère que tu sais comment tu vas te débrouiller hyn joli garçon 🙏. ",
            "C'est donc ça l'apocalypse financière ? Budget {category} : {percentage}%. 💀 On en parle ?",
            "Bravooooo, ton budget du mois pour {category} est consommé à {percentage}%. \n😚 Ta bouche va tellement sentir gari."
        ],
        'global_budget' => [
            "🚨 DING DING DING ! Budget global mensuel dépassé ! Tu vis dans quel monde ?",
            "C'est fini. Budget global explosé. 💸 On attend le prochain mois ou on fait un miracle ?",
            "Nikolaii ne fais que constater : budget global dépassé. 😮‍💨 Besoin d'un plan de sauvetage ?",
            "Nouvelle tendance : dépenser plus que ce qu'on a. Budget global : OUT."
        ],
        'inactivity_24h' => [
            "Coucou toi ! 👋 24h sans nouvelle dépense. Tout va bien ?",
            "Hey ! 📊 24h sans enregistrement. Tu as oublié ou tu es devenu économe ?",
            "Je... M'inquiète... Sérieusement. 😟 24h sans dépense enregistrée. Tout est OK ?",
            "Allô ? 📞 24h de silence radio. Besoin d'aide pour enregistrer une dépense ?",
            "Tu m'évite quoi ? Tu as honte de me dire ? Viens orh je vais pas me facher 🙂‍↔️."
        ],

        'inactivity_48h' => [
            "Bon, là ça devient sérieux... 😠 48h sans dépense enregistrée. On se réveille ?",
            "48h de silence. 🤨 Soit tu es devenu ultra-économe, soit tu oublies tout !",
            "Je suis perplexe. 48h sans nouvelle. 🧐 Tu gères tes finances comment ?",
            "ALERTE : 48h sans activité. 🚨 On reprend le contrôle dès maintenant !",
            "Moi je vais appeler la police pour signaler ta disparition hyn 😑"
        ],

        'month_archived' => [
            "Mois archivé ! 🎉 Bilan {month} : {expenses} dépensés, {savings} épargnés. {emoji}",
            "C'est dans la boîte ! 📦 {month} archivé. Dépenses : {expenses}, Épargne : {savings}. {emoji}",
            "Nikolaii valide l'archivage ! ✅ {month} : {expenses} dépensés, {savings} sauvegardés. {emoji}",
            "Chapitre terminé ! 📖 {month} archivé. Performance : {expenses}/{savings}. {emoji}",
            "🥳 C'est la fin du mois! {month} est terminé et archivé.\n Tu as dépensé {expenses} et éconimisé {savings}. {emoji}"
        ],

        'low_spending' => [
            "Wow ! Dépenses faibles cette semaine. 🎯 Tu devrais donner des cours !",
            "Impressionnant ! 🙌 Dépenses très contrôlées. Tu mérites une médaille !",
            "ÇA ! Ça ça fait plaisir 😍 Dépenses minimales. Continue !",
            "Quelle discipline ! 💪 Dépenses ultra-maîtrisées. Tu es un modèle !",
            "Côté Agny arrctivé ! Tu gère mon fils continue comme ça! 🕺"
        ],

        'daily_limit' => [
            "🚨 ATTENTION ! Tu as dépensé {daily_total} aujourd'hui ! La limite de 10 000 FCFA/jour est dépassée. {emoji}",
            "🤯 Wow ! {daily_total} en un seul jour ? Tu devrais ralentir un peu...",
            "Tchai tu fais chier 😠. {daily_total} dépensés aujourd'hui ! C'est trop !",
            "Alerte dépenses ! {daily_total} aujourd'hui. 📊 On revoit le budget ensemble ?"
        ],
        
        'daily_warning' => [
            "⚠️ Attention : {daily_total} dépensés aujourd'hui. Tu approches de la limite de 10 000 FCFA/jour.",
            "Psst... {daily_total} aujourd'hui. 👀 Encore un peu et tu dépasses la limite journalière !",
            "Comme tu veux pas faire attention moi même je vais surveiller tes dépenses : {daily_total} aujourd'hui. 🧐 On reste vigilant pour ne pas dépasser 10 000 FCFA ?"
        ],

        'goal_achieved' => [
            "🎉 FÉLICITATIONS ! Tu as atteint ton objectif '{goal_name}' de {amount} ! 🏆",
            "👑 Objectif accompli ! {amount} épargnés pour {goal_name}. Tu gères !",
            "Je suis impressionné ! 🎯 '{goal_name}' atteint : {amount}. Prochain défi ?"
        ],
        'goal_progress' => [
            "🚀 Super progression ! '{goal_name}' : {percentage}% atteints. Plus que {remaining} !",
            "💪 Tu y es presque ! '{goal_name}' : {percentage}%. Encore {remaining} à épargner !", 
            "C'est super ! : '{goal_name}' à {percentage}%. 📈 Continue comme ça !",
            "C'est petit à petit que l'oiseau fais son nid. \n '{goal_name}' à {percentage}%. On continue 📈  !"
        ]

    ];

    // Espace signatures envoyées (anti-spam par base de données via meta)

    public function monthArchived(string $month): void {
        $text = $this->renderTemplate('month_archived', ['month' => $month]);
        $this->sendMessage($text);
    }
    
    // Fonction globale accessible
    function alertMonthArchived(string $month): void {
        global $__nikolaii; $__nikolaii->monthArchived($month);
    }

    private function createAlertSignature(string $type, ?string $category=null, ?float $amount=null, ?string $dateKey=null): string {
        $parts = [$type];
        if ($category) $parts[] = $category;
        if ($amount !== null) $parts[] = (string)round($amount);
        if ($dateKey) $parts[] = $dateKey;
        return implode('|', $parts);
    }

    private function isAlertAlreadySent(string $signature): bool {
    $sent = getMeta('telegram_sent_alerts');
    $sentArr = $sent ? json_decode($sent, true) : [];
    return in_array($signature, $sentArr, true);
    }

    private function markAlertAsSent(string $signature): void {
        $sent = getMeta('telegram_sent_alerts');
        $sentArr = $sent ? json_decode($sent, true) : [];
        $sentArr[] = $signature;
        if (count($sentArr) > 500) {
            $sentArr = array_slice($sentArr, -300);
        }
        setMeta('telegram_sent_alerts', json_encode($sentArr));
    }

    private function sanitizeMessage(string $text): string {
        // Telegram accepte le texte brut ; on neutralise juste quelques caractères
        return trim($text);
    }

    public function sendMessage(string $message): bool {
        $message = $this->sanitizeMessage($message);
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => CHAT_ID,
            'text'    => $message
        ]);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Si l'environnement n'a pas internet, on renvoie true pour ne pas bloquer l'app
        if ($response === false && !empty($err)) {
            return false;
        }
        return true;
    }

    public function renderTemplate(string $key, array $vars): string {
        $tpls = $this->messages[$key] ?? ["Alert: $key"];
        $tpl  = $tpls[array_rand($tpls)];
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{' . $k . '}', $v, $tpl);
        }
        return $tpl;
    }

    // ====== Messages concrets ======
    public function largeExpense(array $expense): void {
        $signature = $this->createAlertSignature('large_expense', $expense['category'], floatval($expense['amount']), (string)$expense['id']);
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate('large_expense', [
            'amount'   => number_format($expense['amount'], 0, ',', ' ') . ' ' . CURRENCY,
            'category' => $expense['category']
        ]);
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }

    public function budgetWarning(string $category, float $percentage): void {
        $signature = $this->createAlertSignature('budget_warning', $category, null, date('Y-m-d-H'));
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate('budget_warning', [
            'category'   => $category,
            'percentage' => number_format($percentage, 1)
        ]);
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }

    public function budgetExceeded(string $category, float $percentage): void {
        $signature = $this->createAlertSignature('budget_exceeded', $category, null, date('Y-m-d-H'));
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate('budget_exceeded', [
            'category'   => $category,
            'percentage' => number_format($percentage, 1)
        ]);
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }

    public function globalBudget(float $total, float $budget): void {
        $signature = $this->createAlertSignature('global_budget', null, null, date('Y-m-d-H'));
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate('global_budget', []);
        $text .= "\nTotal: " . number_format($total, 0, ',', ' ') . ' ' . CURRENCY . " / Budget: " . number_format($budget, 0, ',', ' ') . ' ' . CURRENCY;
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }

    public function inactivity(int $days): void {
        $key = $days >= 48 ? 'inactivity_48h' : 'inactivity_24h';
        $signature = $this->createAlertSignature($key, null, null, date('Y-m-d'));
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate($key, []);
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }

    public function lowSpending(): void {
        $signature = $this->createAlertSignature('low_spending', null, null, date('Y-m-d-H'));
        if ($this->isAlertAlreadySent($signature)) return;

        $text = $this->renderTemplate('low_spending', []);
        if ($this->sendMessage($text)) $this->markAlertAsSent($signature);
    }
}

// =============================
// Fonctions globales attendues
// =============================
$__nikolaii = new Nikolaii();

function alertLargeExpense(array $expense): void {
    global $__nikolaii; $__nikolaii->largeExpense($expense);
}
function alertBudgetWarning(string $category, float $percentage): void {
    global $__nikolaii; $__nikolaii->budgetWarning($category, $percentage);
}
function alertBudgetExceeded(string $category, float $percentage): void {
    global $__nikolaii; $__nikolaii->budgetExceeded($category, $percentage);
}
function alertGlobalBudgetExceeded(float $total, float $budget): void {
    global $__nikolaii; $__nikolaii->globalBudget($total, $budget);
}
function alertLowSpending(): void {
    global $__nikolaii; $__nikolaii->lowSpending();
}

// Vérifications périodiques (déclenchées par l'app après actions)
function checkInactivityAlerts(): void {
    // Pas de dépense depuis X jours -> notifier
    $userId = ensure_default_user();
    $expenses = fetchExpenses($userId);
    $days = 0;
    if (!empty($expenses)) {
        $last = $expenses[0];
        $lastDate = strtotime($last['date']);
        $days = floor((time() - $lastDate) / 86400);
    } else {
        $days = 7;
    }
    if ($days >= 7) {
        global $__nikolaii; $__nikolaii->inactivity($days);
        insertAlert($userId, 'inactivity', "Plus de dépenses depuis $days jours.");
    }
}

function checkAndSendAlerts(): void {
    // anti-fréquence: pas plus d'une vérif/30s
    $now = time();
    $lastCheck = getMeta('last_alert_check');
    if ($lastCheck && ($now - intval($lastCheck)) < 30) return;
    setMeta('last_alert_check', $now);

    $userId = ensure_default_user();
    $budgets = getBudgets($userId);
    $expenses = fetchExpenses($userId);

    // Define alert types: hourly rotation vs immediate
    $hourlyAlertTypes = ['large_expense', 'budget_warning', 'budget_exceeded', 'global_budget'];

    // Get current alert type in rotation
    $currentAlertIndex = intval(getMeta('current_alert_rotation') ?? 0);
    $currentAlertType = $hourlyAlertTypes[$currentAlertIndex];

    // Check if today is an active day (every 2 days: even day of year)
    $day_of_year = date('z'); // 0-365
    $is_active_day = ($day_of_year % 2 == 0);

    // Check if current hour is active (00h,01h,02h,03h,06h,07h,08h,09h)
    $current_hour = date('G'); // 0-23
    $active_hours = [0, 1, 2, 3, 6, 7, 8, 9];
    $is_active_hour = in_array($current_hour, $active_hours);

    // Send only ONE hourly alert per execution if active day and hour
    $alertSent = false;

    if ($is_active_day && $is_active_hour && $currentAlertType === 'large_expense' && !$alertSent) {
        // 0) Dépenses importantes
        foreach ($expenses as $expense) {
            if ($expense['amount'] > 10000) {
                alertLargeExpense($expense);
                insertAlert($userId, 'large_expense', "Dépense de " . formatCurrency($expense['amount']) . " en " . $expense['category']);
                $alertSent = true;
                break; // Send only one large expense alert per hour
            }
        }
    }

    if ($is_active_day && $is_active_hour && $currentAlertType === 'budget_warning' && !$alertSent) {
        // 1) Avertissements budget par catégorie
        foreach ($budgets as $category => $budget) {
            $budget = floatval($budget);
            if ($budget <= 0) continue;
            $spent = 0.0;
            foreach ($expenses as $e) {
                if ($e['category'] === $category) $spent += floatval($e['amount']);
            }
            $pct = ($spent / $budget) * 100.0;
            if ($pct >= 80 && $pct < 100) {
                alertBudgetWarning($category, $pct);
                insertAlert($userId, 'budget_warning', "Attention: $category à " . number_format($pct, 1) . "% du budget.");
                $alertSent = true;
                break; // Send only one budget warning per hour
            }
        }
    }

    if ($is_active_day && $is_active_hour && $currentAlertType === 'budget_exceeded' && !$alertSent) {
        // 1) Dépassements budget par catégorie
        foreach ($budgets as $category => $budget) {
            $budget = floatval($budget);
            if ($budget <= 0) continue;
            $spent = 0.0;
            foreach ($expenses as $e) {
                if ($e['category'] === $category) $spent += floatval($e['amount']);
            }
            $pct = ($spent / $budget) * 100.0;
            if ($pct >= 100) {
                alertBudgetExceeded($category, $pct);
                insertAlert($userId, 'budget_exceeded', "Budget $category dépassé de " . number_format($pct, 1) . "%.");
                $alertSent = true;
                break; // Send only one budget exceeded alert per hour
            }
        }
    }

    if ($is_active_day && $is_active_hour && $currentAlertType === 'global_budget' && !$alertSent) {
        // 2) Dépassement budget global
        $meta = getMeta('monthly_budget');
        $monthlyBudget = $meta ? floatval($meta) : 0.0;
        $total = 0.0;
        foreach ($expenses as $e) $total += floatval($e['amount']);
        if ($monthlyBudget > 0 && $total > $monthlyBudget) {
            alertGlobalBudgetExceeded($total, $monthlyBudget);
            insertAlert($userId, 'global_budget_exceeded', "Budget global dépassé.");
            $alertSent = true;
        }
    }

    // Rotate to next alert type for next hour
    $nextIndex = ($currentAlertIndex + 1) % count($hourlyAlertTypes);
    setMeta('current_alert_rotation', $nextIndex);

    // ===== IMMEDIATE ALERTS (sent right away) =====

    // 3) Encouragement si très peu de dépenses en début de mois
    $day = intval(date('j'));
    if ($day <= 7) {
        $totalWeek = 0.0;
        $monday = strtotime('monday this week');
        foreach ($expenses as $e) {
            if (strtotime($e['date']) >= $monday) {
                $totalWeek += floatval($e['amount']);
            }
        }
        if ($totalWeek < 5000) {
            alertLowSpending();
            insertAlert($userId, 'low_spending', "Dépenses faibles cette semaine.");
        }
    }

    $nikolaii = new Nikolaii();
    $today = date('Y-m-d');

    // Vérifier les dépenses journalières (immediate)
    checkDailyExpenses($nikolaii);

    // Vérifier les objectifs d'épargne (immediate)
    checkSavingGoals($nikolaii);

    // 4) Inactivité (immediate)
    checkInactivityAlerts();
}

function checkDailyExpenses($nikolaii) {
    $userId = ensure_default_user();
    $expenses = fetchExpenses($userId);
    if (empty($expenses)) return;
    $lastCheck = getMeta('last_daily_check');
    if ($lastCheck && (time() - intval($lastCheck) < 14400)) return;
    setMeta('last_daily_check', time());
    $today = date('Y-m-d');
    $dailyTotal = 0;
    foreach ($expenses as $expense) {
        if ($expense['date'] === $today) {
            $dailyTotal += $expense['amount'];
        }
    }
    if ($dailyTotal > 10000) {
        $text = $nikolaii->renderTemplate('daily_limit', [
            'daily_total' => formatCurrency($dailyTotal),
            'emoji' => $dailyTotal > 15000 ? '💸' : '⚠️'
        ]);
        $nikolaii->sendMessage($text);
        insertAlert($userId, 'daily_limit', "Limite journalière dépassée: " . formatCurrency($dailyTotal));
    } elseif ($dailyTotal > 8000) {
        $text = $nikolaii->renderTemplate('daily_warning', [
            'daily_total' => formatCurrency($dailyTotal)
        ]);
        $nikolaii->sendMessage($text);
        insertAlert($userId, 'daily_warning', "Attention: " . formatCurrency($dailyTotal) . " dépensés aujourd'hui.");
    }
}
function checkSavingGoals($nikolaii) {
    $userId = ensure_default_user();
    $goals = getMeta('saving_goals');
    $goalsArr = $goals ? json_decode($goals, true) : [];
    if (empty($goalsArr)) return;
    $lastCheck = getMeta('last_goals_check');
    if ($lastCheck && (time() - intval($lastCheck) < 86400)) return;
    setMeta('last_goals_check', time());
    foreach ($goalsArr as $goal) {
        $percentage = ($goal['current'] / $goal['target']) * 100;
        if ($percentage >= 100) {
            $text = $nikolaii->renderTemplate('goal_achieved', [
                'goal_name' => $goal['name'],
                'amount' => formatCurrency($goal['target'])
            ]);
            $nikolaii->sendMessage($text);
            insertAlert($userId, 'goal_achieved', "Objectif '" . $goal['name'] . "' atteint!");
        } elseif ($percentage >= 75) {
            $text = $nikolaii->renderTemplate('goal_progress', [
                'goal_name' => $goal['name'],
                'percentage' => round($percentage),
                'remaining' => formatCurrency($goal['target'] - $goal['current'])
            ]);
            $nikolaii->sendMessage($text);
            insertAlert($userId, 'goal_progress', "Objectif '" . $goal['name'] . "' à " . round($percentage) . "%.");
        }
    }
}
?>
