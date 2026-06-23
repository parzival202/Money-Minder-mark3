<?php
// ============================================================
// admin.php - Panneau d'administration MoneyMinder
// ============================================================
require_once __DIR__ . '/auth.php';

requireAdmin();

$adminService = new App\Services\AdminService();
$current_admin_id = (int)$_SESSION['user_id'];
$error = null;
$success = null;
$show_self_edit = isset($_GET['edit_self']);

if (isset($_GET['self_updated'])) {
    $success = 'Vos identifiants ont bien été mis à jour.';
    $show_self_edit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Requête invalide. Veuillez réessayer.';
    } else {
        $state = $adminService->handle($_POST, $_SESSION, $current_admin_id);
        $error = $state['error'];
        $success = $state['success'];
        $show_self_edit = $show_self_edit || $state['show_self_edit'];

        if (!empty($state['redirect'])) {
            header('Location: ' . $state['redirect']);
            exit;
        }
    }
}

$pageData = $adminService->buildPageData($current_admin_id);
$current_admin = $pageData['current_admin'];
$users = $pageData['users'];
$stats = $pageData['stats'];
$csrf = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Administration - MoneyMinder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <style>
        body { background: #EEF2FF; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            margin-bottom: 20px;
        }

        .admin-header {
            background: linear-gradient(135deg, #6D28D9, #4C1D95);
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 0;
            margin-bottom: 1.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border-color: #e5e7eb;
        }

        .form-control:focus, .form-select:focus {
            border-color: #6D28D9;
            box-shadow: 0 0 0 .2rem rgba(109,40,217,.2);
        }

        .btn-action {
            padding: 3px 10px;
            font-size: .78rem;
            border-radius: 6px;
        }

        .badge-admin { background: #DC2626; }
        .badge-user  { background: #6B7280; }
        .password-strength { font-size: .78rem; margin-top: 4px; }
    </style>
</head>
<body>

<div class="admin-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <img src="assets/logo2.png" alt="Logo" height="38">
        <div>
            <div class="fw-bold fs-5">Money Minder - Admin</div>
            <small style="opacity:.8;">Connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-light btn-sm">
            <i class="fas fa-home me-1"></i>App
        </a>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <button name="logout_action" class="btn btn-outline-light btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
            </button>
        </form>
    </div>
</div>

<div class="container pb-5">
    <?php if (isImpersonating()): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
        <span><i class="fas fa-eye me-2"></i>Vous consultez actuellement le compte de <strong><?php echo htmlspecialchars(getImpersonatedUsername()); ?></strong>.</span>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <button name="stop_impersonate" class="btn btn-sm btn-dark">
                <i class="fas fa-times me-1"></i>Revenir à mon compte
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/views/partials/admin_alerts.php'; ?>

    <div class="row">
        <?php include __DIR__ . '/views/partials/admin_sidebar.php'; ?>
        <?php include __DIR__ . '/views/partials/admin_users_table.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/views/partials/admin_modals.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleNewPwd() {
    const input = document.getElementById('newPassword');
    const icon = document.getElementById('newPwdIcon');
    if (!input || !icon) {
        return;
    }

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(pwd) {
    const msg = document.getElementById('strengthMsg');
    if (!msg) {
        return;
    }

    if (!pwd) {
        msg.textContent = '';
        return;
    }

    let score = 0;
    if (pwd.length >= 8) score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;

    const levels = [
        { text: 'Très faible', color: '#DC2626' },
        { text: 'Faible', color: '#F97316' },
        { text: 'Moyen', color: '#EAB308' },
        { text: 'Fort', color: '#22C55E' },
        { text: 'Très fort', color: '#16A34A' },
    ];
    const level = levels[Math.min(score, 4)];
    msg.textContent = 'Force : ' + level.text;
    msg.style.color = level.color;
}

const editUserModal = document.getElementById('editUserModal');

function fillEditUserModal(button) {
    document.getElementById('editUserId').value = button.getAttribute('data-id') || '';
    document.getElementById('editUsername').value = button.getAttribute('data-username') || '';
    document.getElementById('editFirstName').value = button.getAttribute('data-first-name') || '';
    document.getElementById('editLastName').value = button.getAttribute('data-last-name') || '';
    document.getElementById('editPassword').value = '';
    document.getElementById('editIsAdmin').checked = button.getAttribute('data-is-admin') === '1';
}

function openEditUserModal(button) {
    if (!editUserModal || !button || typeof bootstrap === 'undefined') {
        return false;
    }

    fillEditUserModal(button);
    const modal = bootstrap.Modal.getOrCreateInstance(editUserModal);
    modal.show();
    return false;
}

document.getElementById('telegramModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    if (!btn) {
        return;
    }

    document.getElementById('telegramUserId').value = btn.dataset.uid || '';
    document.getElementById('telegramUsername').textContent = btn.dataset.username || '';
    document.getElementById('telegramBotToken').value = btn.dataset.token || '';
    document.getElementById('telegramChatId').value = btn.dataset.chatid || '';

    const statusEl = document.getElementById('telegramStatus');
    if (!statusEl) {
        return;
    }

    if (btn.dataset.token && btn.dataset.chatid) {
        statusEl.className = 'alert alert-success py-2 small';
        statusEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>Telegram configuré pour cet utilisateur.';
    } else {
        statusEl.className = 'alert alert-warning py-2 small';
        statusEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Telegram non configuré.';
    }

    statusEl.classList.remove('d-none');
});
</script>
</body>
</html>
