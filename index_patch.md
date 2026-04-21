# Modifications à appliquer dans `index.php`

Trois changements seulement. Le reste du fichier ne change pas.

---

## Changement 1 — Tout en haut du fichier (lignes 1 à 9)

**REMPLACE :**
```php
<?php
// ============================================================
// CONFIGURATION
// ============================================================
date_default_timezone_set('Africa/Abidjan');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
init_db();
$user_id = ensure_default_user();
```

**PAR :**
```php
<?php
// ============================================================
// CONFIGURATION
// ============================================================
date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/auth.php'; // démarre la session + fournit requireAuth()
requireAuth();                       // redirige vers login.php si non connecté

require_once __DIR__ . '/db.php';
init_db();
$user_id = getCurrentUserId();       // respecte l'impersonation admin
```

---

## Changement 2 — Dans le bloc POST, tout au début (avant le premier if)

**AJOUTE ces deux handlers juste après `if ($_SERVER['REQUEST_METHOD'] === 'POST') {` :**

```php
    // Déconnexion
    if (isset($_POST['logout_action'])) {
        logout();
        header('Location: login.php'); exit;
    }

    // Arrêter l'impersonation (bouton dans la bannière)
    if (isset($_POST['stop_impersonate'])) {
        unset($_SESSION['impersonate_user_id']);
        header('Location: index.php'); exit;
    }
```

---

## Changement 3 — Dans le HTML du header

**TROUVE cette div dans le header :**
```html
<div class="d-flex align-items-center gap-2 flex-wrap">
    <a href="archives.php" class="btn btn-outline-secondary btn-sm">
        Archives <i class="fas fa-archive ms-1"></i>
    </a>
    <span class="badge ...">
```

**AJOUTE ces boutons APRÈS le lien Archives et AVANT le badge Épargne :**
```php
<?php if ($_SESSION['is_admin']): ?>
<a href="admin.php" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-shield-alt me-1"></i>Admin
</a>
<?php endif; ?>

<form method="POST" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <button name="logout_action" class="btn btn-outline-danger btn-sm">
        <i class="fas fa-sign-out-alt me-1"></i>Déco
    </button>
</form>
```

**AJOUTE également la bannière d'impersonation JUSTE AVANT `<header` :**
```php
<?php if (isImpersonating()): ?>
<div class="bg-warning text-dark text-center py-2" style="font-size:.85rem;font-weight:500;">
    <i class="fas fa-eye me-2"></i>
    Vous consultez le compte de <strong><?php echo htmlspecialchars(getImpersonatedUsername()); ?></strong>
    <form method="POST" class="d-inline ms-3">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <button name="stop_impersonate" class="btn btn-sm btn-dark py-0 px-2" style="font-size:.8rem;">
            <i class="fas fa-times me-1"></i>Revenir à mon compte
        </button>
    </form>
</div>
<?php endif; ?>
```

---

## Récapitulatif des fichiers

| Fichier        | Action   | Description                                    |
|----------------|----------|------------------------------------------------|
| `db.php`       | Remplacer | Nouvelle version avec login_attempts + helpers |
| `auth.php`     | Créer     | Système d'auth complet (session, CSRF, guards) |
| `login.php`    | Créer     | Page de connexion sécurisée                    |
| `admin.php`    | Créer     | Panneau admin (créer/supprimer/voir les users) |
| `index.php`    | 3 patches | Top du fichier + 2 handlers POST + header HTML |

---

## Première connexion

Lors du premier lancement après ces modifications, le système va :
1. Créer automatiquement un user **admin** avec le mot de passe **`changeme`**
2. Tu seras redirigé vers `login.php`
3. Connecte-toi avec `admin` / `changeme`
4. **Va immédiatement dans le panneau Admin et change le mot de passe** en supprimant le compte admin et en en créant un nouveau avec un vrai mot de passe sécurisé.
