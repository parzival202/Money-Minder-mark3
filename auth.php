<?php
// ============================================================
// auth.php — Système d'authentification MoneyMinder
// ============================================================

// Démarrage sécurisé de la session
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/data/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0755, true);
    }
    session_save_path($sessionPath);
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

require_once __DIR__ . '/db.php';

// ============================================================
// PROTECTION CSRF
// ============================================================

/**
 * Génère (ou retourne) le token CSRF de la session courante.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF soumis via POST.
 * Utilise hash_equals() pour éviter les timing attacks.
 */
function verifyCsrfToken(string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// AUTHENTIFICATION
// ============================================================

/**
 * Tente une connexion.
 * Retourne ['success' => bool, 'error' => string|null].
 */
function attemptLogin(string $username, string $password): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Brute force — max 5 tentatives par username+IP sur 15 min
    if (countRecentAttempts($username, $ip) >= 5) {
        return [
            'success' => false,
            'error'   => 'Trop de tentatives échouées. Réessayez dans 15 minutes.',
        ];
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, password_hash, is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        recordFailedAttempt($username, $ip);
        return [
            'success' => false,
            'error'   => 'Nom d\'utilisateur ou mot de passe incorrect.',
        ];
    }

    // Succès — regénère l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);
    clearLoginAttempts($username);

    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['first_name'] = $user['first_name'] ?? '';
    $_SESSION['last_name'] = $user['last_name'] ?? '';
    $_SESSION['is_admin']  = (bool)$user['is_admin'];
    $_SESSION['logged_in'] = true;

    return ['success' => true, 'error' => null];
}

/**
 * Déconnecte l'utilisateur et détruit la session complètement.
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $p['path'], $p['domain'],
            $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

// ============================================================
// GUARDS (à appeler en haut de chaque page protégée)
// ============================================================

/**
 * Redirige vers login.php si l'utilisateur n'est pas connecté.
 */
function requireAuth(): void {
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Redirige vers index.php si l'utilisateur n'est pas admin.
 */
function requireAdmin(): void {
    requireAuth();
    if (empty($_SESSION['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}

// ============================================================
// HELPERS DE SESSION
// ============================================================

/**
 * Retourne l'ID de l'utilisateur actif.
 * Si un admin est en mode impersonation, retourne l'ID de l'utilisateur impersonné.
 */
function getCurrentUserId(): int {
    if (!empty($_SESSION['is_admin']) && !empty($_SESSION['impersonate_user_id'])) {
        return (int)$_SESSION['impersonate_user_id'];
    }
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Retourne true si un admin est en train de consulter le compte d'un autre utilisateur.
 */
function isImpersonating(): bool {
    return !empty($_SESSION['is_admin']) && !empty($_SESSION['impersonate_user_id']);
}

/**
 * Retourne le username de l'utilisateur impersonné (pour l'afficher dans la bannière).
 */
function getImpersonatedUsername(): string {
    if (!isImpersonating()) return '';
    global $pdo;
    $stmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['impersonate_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return getUserDisplayName($user);
}
