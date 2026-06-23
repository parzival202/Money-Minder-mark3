# Intégration Telegram par utilisateur

## Fichier 1 — `telegram_bot.php`
Remplace entièrement l'ancien fichier par le nouveau fourni.
Aucune autre modification nécessaire dans ce fichier.

---

## Fichier 2 — `db.php` : migration colonnes Telegram

Dans la fonction `init_db()`, ajoute ces deux migrations
juste après les migrations `first_name` / `last_name` existantes :

```php
// Migration : colonnes Telegram par utilisateur
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN telegram_bot_token TEXT NOT NULL DEFAULT ''");
} catch (Exception $e) { /* déjà présente */ }
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN telegram_chat_id TEXT NOT NULL DEFAULT ''");
} catch (Exception $e) { /* déjà présente */ }
```

Puis ajoute cette fonction avec les autres fonctions USERS
(après `updateUserAccount`) :

```php
/**
 * Met à jour les credentials Telegram d'un utilisateur.
 */
function updateUserTelegram(int $userId, string $botToken, string $chatId): bool {
    global $pdo;
    return $pdo->prepare(
        "UPDATE users SET telegram_bot_token = ?, telegram_chat_id = ? WHERE id = ?"
    )->execute([trim($botToken), trim($chatId), $userId]);
}

/**
 * Récupère les credentials Telegram d'un utilisateur.
 */
function getUserTelegram(int $userId): array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT telegram_bot_token, telegram_chat_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['telegram_bot_token' => '', 'telegram_chat_id' => ''];
}
```

Et modifie `getMeta` dans `telegram_bot.php` pour lire depuis la table
`users` en priorité — c'est déjà géré dans le nouveau `telegram_bot.php`
via `getUserCredentials()` qui appelle `getMeta('telegram_bot_token', '', $userId)`.

**Donc ajoute aussi ces deux fonctions getMeta qui stockent en users :**

Dans `telegram_bot.php`, la méthode `getUserCredentials` lit via `getMeta`.
Il faut que `setMeta('telegram_bot_token', ...)` et `setMeta('telegram_chat_id', ...)`
écrivent bien dans la table `meta` avec la clé `telegram_bot_token_user_X`.
C'est déjà le cas avec ton `buildMetaKey()` existant. ✅

---

## Fichier 3 — `admin.php` : formulaire Telegram par user

### 3a — Ajouter le handler POST

Dans le bloc `if ($_SERVER['REQUEST_METHOD'] === 'POST')`,
ajoute ce handler après le handler `delete_user`.

### 3b — Ajouter le modal Telegram dans la liste des utilisateurs

Dans le tableau des utilisateurs (`<tbody>`), dans la colonne Actions,
ajoute un troisième bouton après "Voir" et "Supprimer".

### 3c — Ajouter le modal Telegram

Ajoute ce modal à la fin du fichier, avant `</body>`.

---

## Tableau récapitulatif des cooldowns

| Type d'alerte         | Fréquence                        |
|-----------------------|----------------------------------|
| Dépense importante    | Immédiat, 1 fois par dépense     |
| Budget catégorie 80%  | 1 fois par jour par catégorie    |
| Budget catégorie 100% | 1 fois par jour par catégorie    |
| Budget global dépassé | 1 fois par jour                  |
| Limite journalière    | 1 fois par jour                  |
| Avertissement journalier | 1 fois par jour               |
| Inactivité 24h        | 1 fois par jour, entre 9h–11h   |
| Inactivité 48h        | 1 fois par jour, entre 9h–11h   |
| Faibles dépenses      | 1 fois par semaine (lundi)       |
| Progression objectif  | 1 fois par semaine par objectif  |
| Objectif atteint      | 1 fois par objectif              |
| Archivage mensuel     | 1 fois par mois                  |
