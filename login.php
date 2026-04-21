<?php
// ============================================================
// login.php — Page de connexion MoneyMinder
// ============================================================
require_once __DIR__ . '/auth.php';

// Déjà connecté → redirige vers l'app
if (!empty($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $result = attemptLogin($username, $password);
            if ($result['success']) {
                header('Location: index.php');
                exit;
            }
            $error = $result['error'];
        }
    }
}

$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Connexion — MoneyMinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body {
            background: #EEF2FF;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }

        .login-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(109,40,217,.18);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #6D28D9 0%, #4C1D95 100%);
            padding: 2.2rem 2rem 1.8rem;
            text-align: center;
        }

        .login-body {
            padding: 2rem;
            background: #fff;
        }

        .form-control {
            border-radius: 10px;
            padding: .65rem 1rem;
            border-color: #e5e7eb;
        }

        .form-control:focus {
            border-color: #6D28D9;
            box-shadow: 0 0 0 .2rem rgba(109,40,217,.2);
        }

        .input-group-text {
            background: #f9fafb;
            border-color: #e5e7eb;
            border-radius: 10px 0 0 10px;
        }

        .input-group .form-control {
            border-radius: 0;
        }

        .input-group .btn-toggle-pwd {
            background: #f9fafb;
            border-color: #e5e7eb;
            border-radius: 0 10px 10px 0;
            color: #6b7280;
        }

        .btn-signin {
            background: linear-gradient(135deg, #6D28D9, #5b21b6);
            border: none;
            border-radius: 10px;
            padding: .75rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: .02em;
            transition: opacity .2s, transform .15s;
        }

        .btn-signin:hover {
            opacity: .92;
            transform: translateY(-1px);
        }

        .alert-danger {
            border-radius: 10px;
            font-size: .9rem;
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <!-- En-tête -->
        <div class="login-header">
            <img src="assets/logo2.png" alt="MoneyMinder" height="56" class="mb-3">
            <h4 class="text-white fw-bold mb-1">Money Minder</h4>
            <p class="text-white mb-0" style="opacity:.8;font-size:.9rem;">Connectez-vous à votre espace</p>
        </div>

        <!-- Formulaire -->
        <div class="login-body">

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-4">
                <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <!-- Username -->
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary" style="font-size:.9rem;">
                        Nom d'utilisateur
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user text-muted"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            name="username"
                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            placeholder="Votre identifiant"
                            required
                            autofocus
                            autocomplete="username">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label class="form-label fw-semibold text-secondary" style="font-size:.9rem;">
                        Mot de passe
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock text-muted"></i>
                        </span>
                        <input
                            type="password"
                            class="form-control"
                            id="passwordInput"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password">
                        <button
                            type="button"
                            class="btn btn-toggle-pwd"
                            onclick="togglePassword()"
                            tabindex="-1"
                            aria-label="Afficher/masquer le mot de passe">
                            <i class="fas fa-eye" id="pwdIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn-signin btn-primary w-100 text-white">
                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                </button>
            </form>
        </div>
    </div>

    <!-- Footer discret -->
    <p class="text-center text-muted mt-3 mb-0" style="font-size:.78rem;">
        MoneyMinder &copy; <?php echo date('Y'); ?>
    </p>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('pwdIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
