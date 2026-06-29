<?php
// ============================================================
// telegram_bot.php — Bot Nikolaii avec credentials par user
// ============================================================

require_once __DIR__ . '/config.php';

class Nikolaii {

    private array $messages = [
        'large_expense' => [
            "😳 Tu es sérieux ? {amount} pour {category} ?!\nTu as perdu la tête ?",
            "{amount} pour {category} ?! 🤯\n\nOn dirait que l'argent pousse dans ton jardin hein !",
            "Mais... {amount} pour {category} ?! 💸 Tu voulais acheter quoi ? l'Afrique de l'Ouest ?",
            "Nikolaii à l'appareil. Code rouge :\n{amount} dépensés en {category}. 🚨 On fait un bilan ?",
            "Aaah Doug Saga ?\nDépenser {amount} dans {category} seulement ? Ce mois là va t'enseigner la vie 😂",
        ],
        'budget_warning' => [
            "Attention chef !\n{category} est à {percentage}% du budget.\n🚧 Faut calmer le jeu !",
            "Psst... {category} : {percentage}% dépensés.\n🫣 Tu veux finir le mois avec des pâtes ?",
            "{category} : {percentage}% du budget.\n😬 On respire un coup et on réfléchit ?",
            "Rapport budget : {category} à {percentage}%. ⚠️ C'est le moment de se poser les bonnes questions.",
            "Toi tu n'as pas dit tu dépenses on dirait tu sais pas compter ?!\n\n{category} est à {percentage}% ooh.\n\n⚠️ ARRÊTE DE TE JOUER LES BÊTES !!",
        ],
        'budget_exceeded' => [
            "C'est officiel : budget {category} explosé ! 💥 {percentage}% dépensés.\nComment on fait maintenant ?",
            "ALERTE ROUGE ! Budget {category} dépassé à {percentage}%.\n🚨 On repart sur de nouvelles bases ?",
            "De la manière le budget {category} est dépassé à {percentage}% là… j'espère que tu sais comment tu vas te débrouiller hyn joli garçon 🙏.",
            "C'est donc ça l'apocalypse financière ? Budget {category} : {percentage}%. 💀 On en parle ?",
            "Bravooooo, ton budget du mois pour {category} est consommé à {percentage}%.\n😚 Ta bouche va tellement sentir gari.",
        ],
        'global_budget' => [
            "🚨 DING DING DING ! Budget global mensuel dépassé ! Tu vis dans quel monde ?",
            "C'est fini. Budget global explosé. 💸 On attend le prochain mois ou on fait un miracle ?",
            "Nikolaii ne fait que constater : budget global dépassé. 😮‍💨 Besoin d'un plan de sauvetage ?",
            "Nouvelle tendance : dépenser plus que ce qu'on a. Budget global : OUT.",
        ],
        'inactivity_24h' => [
            "Coucou toi ! 👋 24h sans nouvelle dépense. Tout va bien ?",
            "Hey ! 📊 24h sans enregistrement. Tu as oublié ou tu es devenu économe ?",
            "Je... M'inquiète... Sérieusement. 😟 24h sans dépense enregistrée. Tout est OK ?",
            "Allô ? 📞 24h de silence radio. Besoin d'aide pour enregistrer une dépense ?",
            "Tu m'évites quoi ? Tu as honte de me dire ? Viens orh je vais pas me fâcher 🙂‍↔️.",
        ],
        'inactivity_48h' => [
            "Bon, là ça devient sérieux... 😠 48h sans dépense enregistrée. On se réveille ?",
            "48h de silence. 🤨 Soit tu es devenu ultra-économe, soit tu oublies tout !",
            "Je suis perplexe. 48h sans nouvelle. 🧐 Tu gères tes finances comment ?",
            "ALERTE : 48h sans activité. 🚨 On reprend le contrôle dès maintenant !",
            "Moi je vais appeler la police pour signaler ta disparition hyn 😑",
        ],
        'month_archived' => [
            "Mois archivé ! 🎉 Bilan {month} : {expenses} dépensés, {savings} épargnés. {emoji}",
            "C'est dans la boîte ! 📦 {month} archivé. Dépenses : {expenses}, Épargne : {savings}. {emoji}",
            "Nikolaii valide l'archivage ! ✅ {month} : {expenses} dépensés, {savings} sauvegardés. {emoji}",
            "Chapitre terminé ! 📖 {month} archivé. Performance : {expenses}/{savings}. {emoji}",
            "🥳 C'est la fin du mois ! {month} est terminé et archivé.\nTu as dépensé {expenses} et économisé {savings}. {emoji}",
        ],
        'low_spending' => [
            "Wow ! Dépenses faibles cette semaine. 🎯 Tu devrais donner des cours !",
            "Impressionnant ! 🙌 Dépenses très contrôlées. Tu mérites une médaille !",
            "ÇA ! Ça ça fait plaisir 😍 Dépenses minimales. Continue !",
            "Quelle discipline ! 💪 Dépenses ultra-maîtrisées. Tu es un modèle !",
            "Côté Agny arrctivé ! Tu gères mon fils continue comme ça ! 🕺",
        ],
        'daily_limit' => [
            "🚨 ATTENTION ! Tu as dépensé {daily_total} aujourd'hui ! La limite de 10 000 FCFA/jour est dépassée. {emoji}",
            "🤯 Wow ! {daily_total} en un seul jour ? Tu devrais ralentir un peu...",
            "Tchai tu fais chier 😠. {daily_total} dépensés aujourd'hui ! C'est trop !",
            "Alerte dépenses ! {daily_total} aujourd'hui. 📊 On revoit le budget ensemble ?",
        ],
        'daily_warning' => [
            "⚠️ Attention : {daily_total} dépensés aujourd'hui. Tu approches de la limite de 10 000 FCFA/jour.",
            "Psst... {daily_total} aujourd'hui. 👀 Encore un peu et tu dépasses la limite journalière !",
            "Comme tu veux pas faire attention moi même je vais surveiller tes dépenses : {daily_total} aujourd'hui. 🧐 On reste vigilant pour ne pas dépasser 10 000 FCFA ?",
        ],
        'goal_achieved' => [
            "🎉 FÉLICITATIONS ! Tu as atteint ton objectif '{goal_name}' de {amount} ! 🏆",
            "👑 Objectif accompli ! {amount} épargnés pour {goal_name}. Tu gères !",
            "Je suis impressionné ! 🎯 '{goal_name}' atteint : {amount}. Prochain défi ?",
        ],
        'goal_progress' => [
            "🚀 Super progression ! '{goal_name}' : {percentage}% atteints. Plus que {remaining} !",
            "💪 Tu y es presque ! '{goal_name}' : {percentage}%. Encore {remaining} à épargner !",
            "C'est super ! '{goal_name}' à {percentage}%. 📈 Continue comme ça !",
            "C'est petit à petit que l'oiseau fait son nid.\n'{goal_name}' à {percentage}%. On continue 📈 !",
        ],
        'money_guard_status' => [
            "{status_label} aujourd'hui : {message}\nDépensé : {today_spent}\nReste conseillé : {remaining_today}",
        ],
        'daily_checkin_reminder' => [
            "Check-in du jour manquant.\nDis-moi si tu as dépensé aujourd'hui ou si c'était une journée sans dépense.",
        ],
    ];

    // ──────────────────────────────────────────────────────────
    // CREDENTIALS PAR USER
    // ──────────────────────────────────────────────────────────

    /**
     * Récupère le token et le chat_id Telegram d'un utilisateur.
     * Retourne null si non configuré.
     */
    private function getUserCredentials(int $userId): ?array {
        $token   = getMeta('telegram_bot_token', '', $userId);
        $chat_id = getMeta('telegram_chat_id', '', $userId);

        // Fallback sur les constantes globales si disponibles et user non configuré
        if (empty($token) && defined('BOT_TOKEN'))  $token   = BOT_TOKEN;
        if (empty($chat_id) && defined('CHAT_ID'))  $chat_id = CHAT_ID;

        if (empty($token) || empty($chat_id)) return null;

        return ['token' => $token, 'chat_id' => $chat_id];
    }

    /**
     * Vérifie si un user a Telegram configuré.
     */
    public function isConfigured(int $userId): bool {
        return $this->getUserCredentials($userId) !== null;
    }

    // ──────────────────────────────────────────────────────────
    // ANTI-SPAM (signatures + cooldowns)
    // ──────────────────────────────────────────────────────────

    private function createSignature(string $type, ?string $extra = null): string {
        $parts = [$type];
        if ($extra) $parts[] = $extra;
        return implode('|', $parts);
    }

    private function isAlreadySent(int $userId, string $signature): bool {
        $sent    = getMeta('telegram_sent_alerts', '', $userId);
        $sentArr = $sent ? json_decode($sent, true) : [];
        return is_array($sentArr) && in_array($signature, $sentArr, true);
    }

    private function markAsSent(int $userId, string $signature): void {
        $sent    = getMeta('telegram_sent_alerts', '', $userId);
        $sentArr = $sent ? json_decode($sent, true) : [];
        if (!is_array($sentArr)) $sentArr = [];
        $sentArr[] = $signature;
        // Garde les 500 dernières signatures, supprime les plus anciennes
        if (count($sentArr) > 500) $sentArr = array_slice($sentArr, -400);
        setMeta('telegram_sent_alerts', json_encode($sentArr), $userId);
    }

    /**
     * Vérifie si le cooldown d'un type d'alerte est respecté.
     * @param int    $userId
     * @param string $type       Clé du cooldown
     * @param int    $seconds    Durée minimale entre deux envois
     */
    private function respectsCooldown(int $userId, string $type, int $seconds): bool {
        $key  = 'tg_cooldown_' . $type;
        $last = (int)getMeta($key, 0, $userId);
        if ($last && (time() - $last) < $seconds) return false;
        setMeta($key, time(), $userId);
        return true;
    }

    // ──────────────────────────────────────────────────────────
    // ENVOI
    // ──────────────────────────────────────────────────────────

    public function sendMessage(string $message, int $userId = 0): bool {
        $creds = $userId > 0
            ? $this->getUserCredentials($userId)
            : (defined('BOT_TOKEN') && defined('CHAT_ID')
                ? ['token' => BOT_TOKEN, 'chat_id' => CHAT_ID]
                : null);

        if (!$creds) return false;

        $url = "https://api.telegram.org/bot{$creds['token']}/sendMessage";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'chat_id' => $creds['chat_id'],
                'text'    => trim($message),
            ],
            CURLOPT_TIMEOUT        => 5,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        return ($response !== false && empty($err));
    }

    public function renderTemplate(string $key, array $vars = []): string {
        $tpls = $this->messages[$key] ?? ["Alerte : $key"];
        $tpl  = $tpls[array_rand($tpls)];
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{' . $k . '}', (string)$v, $tpl);
        }
        return $tpl;
    }

    // ──────────────────────────────────────────────────────────
    // ALERTES — chaque méthode gère son propre cooldown
    // ──────────────────────────────────────────────────────────

    /**
     * Dépense importante : immédiat, 1 fois par dépense (jamais répété).
     */
    public function largeExpense(int $userId, array $expense): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('large_expense', (string)$expense['id']);
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('large_expense', [
            'amount'   => formatCurrency($expense['amount']),
            'category' => $expense['category'],
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Budget catégorie à 80% : max 1 fois par jour par catégorie.
     */
    public function budgetWarning(int $userId, string $category, float $percentage): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('budget_warning', $category . '|' . date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('budget_warning', [
            'category'   => $category,
            'percentage' => number_format($percentage, 1),
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Budget catégorie dépassé : max 1 fois par jour par catégorie.
     */
    public function budgetExceeded(int $userId, string $category, float $percentage): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('budget_exceeded', $category . '|' . date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('budget_exceeded', [
            'category'   => $category,
            'percentage' => number_format($percentage, 1),
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Budget global dépassé : max 1 fois par jour.
     */
    public function globalBudget(int $userId, float $total, float $budget): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('global_budget', date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text  = $this->renderTemplate('global_budget', []);
        $text .= "\nTotal : " . formatCurrency($total) . " / Budget : " . formatCurrency($budget);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Inactivité : 1 fois par jour, uniquement entre 9h et 11h.
     */
    public function inactivity(int $userId, int $hours): void {
        if (!$this->isConfigured($userId)) return;

        $currentHour = (int)date('G');
        if ($currentHour < 9 || $currentHour > 11) return; // seulement le matin

        $key = $hours >= 48 ? 'inactivity_48h' : 'inactivity_24h';
        $sig = $this->createSignature($key, date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate($key, []);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Faibles dépenses : 1 fois par semaine max (lundi uniquement).
     */
    public function lowSpending(int $userId): void {
        if (!$this->isConfigured($userId)) return;

        // Uniquement le lundi
        if (date('N') !== '1') return;

        $sig = $this->createSignature('low_spending', date('Y-W')); // semaine ISO
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('low_spending', []);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Limite journalière dépassée : max 1 fois par jour.
     */
    public function dailyLimit(int $userId, float $dailyTotal): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('daily_limit', date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('daily_limit', [
            'daily_total' => formatCurrency($dailyTotal),
            'emoji'       => $dailyTotal > 15000 ? '💸' : '⚠️',
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Avertissement journalier (80% limite) : max 1 fois par jour.
     */
    public function dailyWarning(int $userId, float $dailyTotal): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('daily_warning', date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('daily_warning', [
            'daily_total' => formatCurrency($dailyTotal),
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Objectif épargne atteint : 1 fois par objectif.
     */
    public function goalAchieved(int $userId, string $goalName, float $amount): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('goal_achieved', $goalName);
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('goal_achieved', [
            'goal_name' => $goalName,
            'amount'    => formatCurrency($amount),
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Progression objectif épargne : max 1 fois par semaine par objectif.
     */
    public function goalProgress(int $userId, string $goalName, float $percentage, float $remaining): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('goal_progress', $goalName . '|' . date('Y-W'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('goal_progress', [
            'goal_name'  => $goalName,
            'percentage' => round($percentage),
            'remaining'  => formatCurrency($remaining),
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    /**
     * Archivage mensuel : 1 fois par mois.
     */
    public function monthArchived(int $userId, string $month, float $expenses, float $savings): void {
        $sig = $this->createSignature('month_archived', $month);
        if ($this->isAlreadySent($userId, $sig)) return;

        $emojis = ['🎉', '📦', '✅', '📖', '🥳'];
        $text   = $this->renderTemplate('month_archived', [
            'month'    => $month,
            'expenses' => formatCurrency($expenses),
            'savings'  => formatCurrency($savings),
            'emoji'    => $emojis[array_rand($emojis)],
        ]);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    public function moneyGuardStatus(int $userId, array $guard): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('money_guard_status', ($guard['status'] ?? 'green') . '|' . date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('money_guard_status', [
            'status_label' => $guard['label'] ?? 'Vert',
            'message' => $guard['message'] ?? '',
            'today_spent' => formatCurrency($guard['today_spent'] ?? 0),
            'remaining_today' => formatCurrency($guard['remaining_today'] ?? 0),
        ]);

        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }

    public function dailyCheckinReminder(int $userId): void {
        if (!$this->isConfigured($userId)) return;
        $sig = $this->createSignature('daily_checkin_reminder', date('Y-m-d'));
        if ($this->isAlreadySent($userId, $sig)) return;

        $text = $this->renderTemplate('daily_checkin_reminder', []);
        if ($this->sendMessage($text, $userId)) $this->markAsSent($userId, $sig);
    }
}

// ──────────────────────────────────────────────────────────────
// INSTANCE GLOBALE
// ──────────────────────────────────────────────────────────────
$__nikolaii = new Nikolaii();

// ──────────────────────────────────────────────────────────────
// FONCTIONS GLOBALES (appelées depuis db.php / index.php)
// ──────────────────────────────────────────────────────────────

function alertLargeExpense(int $userId, array $expense): void {
    global $__nikolaii; $__nikolaii->largeExpense($userId, $expense);
}
function alertBudgetWarning(int $userId, string $category, float $percentage): void {
    global $__nikolaii; $__nikolaii->budgetWarning($userId, $category, $percentage);
}
function alertBudgetExceeded(int $userId, string $category, float $percentage): void {
    global $__nikolaii; $__nikolaii->budgetExceeded($userId, $category, $percentage);
}
function alertGlobalBudgetExceeded(int $userId, float $total, float $budget): void {
    global $__nikolaii; $__nikolaii->globalBudget($userId, $total, $budget);
}
function alertLowSpending(int $userId): void {
    global $__nikolaii; $__nikolaii->lowSpending($userId);
}

// ──────────────────────────────────────────────────────────────
// VÉRIFICATIONS PÉRIODIQUES
// Appelées depuis checkAndSendAlerts() après chaque action user
// ──────────────────────────────────────────────────────────────

function checkInactivityAlerts(int $userId): void {
    global $__nikolaii;
    $expenses = fetchExpenses($userId);
    $hours    = 0;

    if (!empty($expenses)) {
        $lastDate = strtotime($expenses[0]['date']);
        $hours    = floor((time() - $lastDate) / 3600);
    } else {
        $hours = 168; // 7 jours si aucune dépense
    }

    if ($hours >= 48) {
        $__nikolaii->inactivity($userId, 48);
        insertAlert($userId, 'inactivity_48h', "Plus de dépenses depuis 48h.");
    } elseif ($hours >= 24) {
        $__nikolaii->inactivity($userId, 24);
        insertAlert($userId, 'inactivity_24h', "Aucune dépense enregistrée depuis 24h.");
    }
}

function checkDailyExpenses(int $userId): void {
    global $__nikolaii;

    // Cooldown : max 1 vérification toutes les 4h
    $lastCheck = getMeta('last_daily_check', 0, $userId);
    if ($lastCheck && (time() - (int)$lastCheck) < 14400) return;
    setMeta('last_daily_check', time(), $userId);

    $expenses   = fetchExpenses($userId);
    $today      = date('Y-m-d');
    $dailyTotal = 0.0;
    foreach ($expenses as $e) {
        if ($e['date'] === $today) $dailyTotal += floatval($e['amount']);
    }

    if ($dailyTotal > 10000) {
        $__nikolaii->dailyLimit($userId, $dailyTotal);
        insertAlert($userId, 'daily_limit', "Limite journalière dépassée : " . formatCurrency($dailyTotal));
    } elseif ($dailyTotal > 8000) {
        $__nikolaii->dailyWarning($userId, $dailyTotal);
        insertAlert($userId, 'daily_warning', "Attention : " . formatCurrency($dailyTotal) . " dépensés aujourd'hui.");
    }
}

function checkSavingGoals(int $userId): void {
    global $__nikolaii;

    // Cooldown : max 1 vérification par jour
    $lastCheck = getMeta('last_goals_check', 0, $userId);
    if ($lastCheck && (time() - (int)$lastCheck) < 86400) return;
    setMeta('last_goals_check', time(), $userId);

    $goalsJson = getMeta('saving_goals_' . $userId, '', null);
    $goals     = $goalsJson ? json_decode($goalsJson, true) : [];
    if (empty($goals)) return;

    foreach ($goals as $goal) {
        if (empty($goal['name']) || empty($goal['target']) || $goal['target'] <= 0) continue;
        $pct = ($goal['current'] / $goal['target']) * 100;

        if ($pct >= 100) {
            $__nikolaii->goalAchieved($userId, $goal['name'], $goal['target']);
            insertAlert($userId, 'goal_achieved', "Objectif '{$goal['name']}' atteint !");
        } elseif ($pct >= 75) {
            $remaining = $goal['target'] - $goal['current'];
            $__nikolaii->goalProgress($userId, $goal['name'], $pct, $remaining);
            insertAlert($userId, 'goal_progress', "Objectif '{$goal['name']}' à " . round($pct) . "%.");
        }
    }
}

function checkLowSpending(int $userId): void {
    global $__nikolaii;
    // Uniquement le lundi matin
    if (date('N') !== '1') return;

    $expenses  = fetchExpenses($userId);
    $monday    = strtotime('monday this week');
    $weekTotal = 0.0;
    foreach ($expenses as $e) {
        if (strtotime($e['date']) >= $monday) $weekTotal += floatval($e['amount']);
    }

    if ($weekTotal < 5000) {
        $__nikolaii->lowSpending($userId);
        insertAlert($userId, 'low_spending', "Dépenses faibles cette semaine.");
    }
}

function checkMoneyGuardSignals(int $userId): void {
    global $__nikolaii;
    $guard = (new App\Services\MoneyGuardService())->evaluate($userId);

    if (in_array($guard['status'] ?? 'green', ['orange', 'red', 'black'], true)) {
        $__nikolaii->moneyGuardStatus($userId, $guard);

        if (($guard['status'] ?? '') === 'black') {
            insertAlert(
                $userId,
                'money_guard_black',
                'Mode strict : stop dépenses aujourd’hui. Il te reste ' . formatCurrency($guard['remaining_today'] ?? 0) . '.'
            );
        } elseif (($guard['status'] ?? '') === 'red') {
            insertAlert(
                $userId,
                'money_guard_red',
                'Tu as déjà dépensé ' . formatCurrency($guard['today_spent'] ?? 0) . ' aujourd’hui. Recommandation : aucune dépense loisir.'
            );
        }
    }
}

function checkDailyCheckinReminder(int $userId): void {
    global $__nikolaii;
    $hasCheckin = (new App\Services\DailyCheckinService())->hasTodayCheckin($userId);
    if (!$hasCheckin) {
        $__nikolaii->dailyCheckinReminder($userId);
    }
}

/**
 * Point d'entrée principal — appelé après chaque action utilisateur.
 * Cooldown global de 2 minutes pour éviter les rafales.
 */
function checkAndSendAlerts(?int $userId = null): void {
    $userId = $userId ?: getContextUserId();
    if (!$userId) return;

    // ── Cooldown global : 1 vérif toutes les 2 minutes max ──
    $lastCheck = getMeta('last_alert_check', 0, $userId);
    if ($lastCheck && (time() - (int)$lastCheck) < 120) return;
    setMeta('last_alert_check', time(), $userId);

    $budgets  = getBudgets($userId);
    $expenses = fetchExpenses($userId);

    // ── 1. Dépense importante (immédiat, par dépense) ────────
    foreach ($expenses as $expense) {
        if (floatval($expense['amount']) > 10000) {
            alertLargeExpense($userId, $expense);
        }
    }

    // ── 2. Budgets catégories (1 fois/jour par catégorie) ────
    foreach ($budgets as $category => $budget) {
        $budget = floatval($budget);
        if ($budget <= 0) continue;

        $spent = 0.0;
        foreach ($expenses as $e) {
            if ($e['category'] === $category) $spent += floatval($e['amount']);
        }
        $pct = ($spent / $budget) * 100.0;

        if ($pct >= 100) {
            alertBudgetExceeded($userId, $category, $pct);
        } elseif ($pct >= 80) {
            alertBudgetWarning($userId, $category, $pct);
        }
    }

    // ── 3. Budget global (1 fois/jour) ───────────────────────
    $monthlyBudget = floatval(getMeta('monthly_budget', 0, $userId));
    if ($monthlyBudget > 0) {
        $total = array_sum(array_column($expenses, 'amount'));
        if ($total > $monthlyBudget) {
            alertGlobalBudgetExceeded($userId, $total, $monthlyBudget);
        }
    }

    // ── 4. Dépenses journalières (toutes les 4h) ─────────────
    checkDailyExpenses($userId);

    // ── 5. Objectifs épargne (1 fois/jour) ───────────────────
    checkSavingGoals($userId);

    // ── 6. Inactivité (1 fois/jour, matin uniquement) ────────
    checkInactivityAlerts($userId);

    // ── 7. Faibles dépenses (lundi uniquement) ───────────────
    checkLowSpending($userId);

    // ── 8. Statut MoneyGuard / check-in ──────────────────────
    checkMoneyGuardSignals($userId);
    checkDailyCheckinReminder($userId);
}
