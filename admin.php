<?php
// ============================================================
// admin.php — Panneau d'administration MoneyMinder
// ============================================================
require_once __DIR__ . '/auth.php';
requireAdmin(); // Redirige vers index.php si pas admin

$current_admin_id = (int)$_SESSION['user_id'];
$current_admin = fetchUserById($current_admin_id);
$error   = null;
$success = null;
$show_self_edit = isset($_GET['edit_self']);
if (isset($_GET['self_updated'])) {
    $success = 'Vos identifiants ont bien Ã©tÃ© mis Ã  jour.';
    $show_self_edit = true;
}

// ── Gestion des actions POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Requête invalide. Veuillez réessayer.';

    } elseif (isset($_POST['logout_action'])) {
        logout();
        header('Location: login.php'); exit;

    } elseif (isset($_POST['stop_impersonate'])) {
        unset($_SESSION['impersonate_user_id']);
        header('Location: admin.php'); exit;

    } elseif (isset($_POST['update_self_account'])) {
        $uname = trim($_POST['self_username'] ?? '');
        $first = trim($_POST['self_first_name'] ?? '');
        $last  = trim($_POST['self_last_name'] ?? '');
        $pwd   = $_POST['self_password'] ?? '';

        if (mb_strlen($uname) < 3) {
            $error = 'Le nom d\'utilisateur doit faire au moins 3 caractÃ¨res.';
            $show_self_edit = true;
        } elseif ($first === '' || $last === '') {
            $error = 'Le nom et le prÃ©nom sont obligatoires.';
            $show_self_edit = true;
        } elseif ($pwd !== '' && (mb_strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9]/', $pwd))) {
            $error = 'Si renseignÃ©, le mot de passe doit faire au moins 8 caractÃ¨res et contenir une lettre et un chiffre.';
            $show_self_edit = true;
        } else {
            $fields = [
                'username' => $uname,
                'first_name' => $first,
                'last_name' => $last,
                'is_admin' => 1,
            ];
            if ($pwd !== '') {
                $fields['password_hash'] = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (updateUserAccount($current_admin_id, $fields)) {
                $_SESSION['username'] = $uname;
                $_SESSION['first_name'] = $first;
                $_SESSION['last_name'] = $last;
                $_SESSION['is_admin'] = true;
                header('Location: admin.php?edit_self=1&self_updated=1#self-edit-form'); exit;
            } else {
                $error = 'Impossible de mettre Ã  jour votre compte. Le nom d\'utilisateur existe peut-Ãªtre dÃ©jÃ .';
                $show_self_edit = true;
            }
        }

    } elseif (isset($_POST['create_user'])) {
        $uname    = trim($_POST['new_username'] ?? '');
        $first    = trim($_POST['new_first_name'] ?? '');
        $last     = trim($_POST['new_last_name'] ?? '');
        $pwd      = $_POST['new_password'] ?? '';
        $is_admin = isset($_POST['new_is_admin']) ? 1 : 0;

        if (mb_strlen($uname) < 3) {
            $error = 'Le nom d\'utilisateur doit faire au moins 3 caractères.';
        } elseif ($first === '' || $last === '') {
            $error = 'Veuillez renseigner le nom et le prÃ©nom.';
        } elseif (mb_strlen($pwd) < 8) {
            $error = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif (!preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9]/', $pwd)) {
            $error = 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        } else {
            $new_id = createUser($uname, $pwd, $is_admin, $first, $last);
            if ($new_id) {
                $success = "Compte \"" . htmlspecialchars($uname) . "\" créé avec succès.";
            } else {
                $error = "Ce nom d'utilisateur existe déjà.";
            }
        }

    } elseif (isset($_POST['update_user'])) {
        $uid      = intval($_POST['edit_user_id'] ?? 0);
        $uname    = trim($_POST['edit_username'] ?? '');
        $first    = trim($_POST['edit_first_name'] ?? '');
        $last     = trim($_POST['edit_last_name'] ?? '');
        $pwd      = $_POST['edit_password'] ?? '';
        $is_admin = isset($_POST['edit_is_admin']) ? 1 : 0;

        if ($uid <= 0) {
            $error = 'Utilisateur invalide.';
        } elseif (mb_strlen($uname) < 3) {
            $error = 'Le nom d\'utilisateur doit faire au moins 3 caractÃ¨res.';
        } elseif ($first === '' || $last === '') {
            $error = 'Le nom et le prÃ©nom sont obligatoires.';
        } elseif ($pwd !== '' && (mb_strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/[0-9]/', $pwd))) {
            $error = 'Si renseignÃ©, le mot de passe doit faire au moins 8 caractÃ¨res et contenir une lettre et un chiffre.';
        } else {
            $fields = [
                'username' => $uname,
                'first_name' => $first,
                'last_name' => $last,
            ];
            if ($pwd !== '') {
                $fields['password_hash'] = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $admin_count = count(array_filter(fetchAllUsers(), fn($u) => (int)$u['is_admin'] === 1));
            if ($uid === $current_admin_id) {
                $is_admin = 1;
            } elseif (!$is_admin && $admin_count <= 1) {
                $error = 'Il faut conserver au moins un administrateur.';
            }

            if (!$error) {
                $fields['is_admin'] = $is_admin;
                if (updateUserAccount($uid, $fields)) {
                    if ($uid === $current_admin_id) {
                        $_SESSION['username'] = $uname;
                        $_SESSION['first_name'] = $first;
                        $_SESSION['last_name'] = $last;
                        $_SESSION['is_admin'] = true;
                        $show_self_edit = true;
                    }
                    $success = 'Compte mis Ã  jour avec succÃ¨s.';
                } else {
                    $error = 'Impossible de mettre Ã  jour ce compte. Le nom d\'utilisateur existe peut-Ãªtre dÃ©jÃ .';
                }
            }
        }

    } elseif (isset($_POST['delete_user'])) {
        $uid = intval($_POST['delete_user']);
        if ($uid === $current_admin_id) {
            $error = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            deleteUser($uid);
            $success = 'Utilisateur supprimé avec toutes ses données.';
        }

    } elseif (isset($_POST['impersonate_user'])) {
        $uid = intval($_POST['impersonate_user']);
        if ($uid !== $current_admin_id) {
            $_SESSION['impersonate_user_id'] = $uid;
            header('Location: index.php'); exit;
        }
    }
}

$current_admin = fetchUserById($current_admin_id);
$users = fetchAllUsers();
$csrf  = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Administration — MoneyMinder</title>
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

<!-- Header -->
<div class="admin-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <img src="assets/logo2.png" alt="Logo" height="38">
        <div>
            <div class="fw-bold fs-5">Money Minder — Admin</div>
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

    <!-- Bannière impersonation active -->
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

    <!-- Alertes -->
    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <div class="row">

        <!-- Créer un utilisateur -->
        <div class="col-md-5">
            <div class="card p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-user-plus me-2 text-success"></i>Créer un utilisateur
                </h6>
                <form method="POST" id="createUserForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Prénom</label>
                        <input type="text" class="form-control form-control-sm" name="new_first_name" placeholder="ex: Jean" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom</label>
                        <input type="text" class="form-control form-control-sm" name="new_last_name" placeholder="ex: Dupont" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom d'utilisateur <span class="text-muted">(min. 3 caractères)</span></label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            name="new_username"
                            placeholder="ex: jean.dupont"
                            minlength="3"
                            required>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Mot de passe <span class="text-muted">(min. 8 caractères)</span></label>
                        <div class="input-group input-group-sm">
                            <input
                                type="password"
                                class="form-control"
                                id="newPassword"
                                name="new_password"
                                placeholder="••••••••"
                                minlength="8"
                                required
                                oninput="checkStrength(this.value)">
                            <button type="button" class="btn btn-outline-secondary" onclick="toggleNewPwd()">
                                <i class="fas fa-eye" id="newPwdIcon"></i>
                            </button>
                        </div>
                        <div class="password-strength text-muted" id="strengthMsg"></div>
                    </div>

                    <div class="mb-3 mt-3 form-check">
                        <input type="checkbox" class="form-check-input" name="new_is_admin" id="newIsAdmin">
                        <label class="form-check-label small" for="newIsAdmin">
                            <i class="fas fa-shield-alt me-1 text-danger"></i>Accès administrateur
                        </label>
                    </div>

                    <button type="submit" name="create_user" class="btn btn-success btn-sm w-100">
                        <i class="fas fa-plus me-1"></i>Créer le compte
                    </button>
                </form>
            </div>

            <!-- Statistiques rapides -->
            <div class="card p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Statistiques</h6>
                <?php
                $total_users  = count($users);
                $admin_users  = count(array_filter($users, fn($u) => $u['is_admin']));
                $normal_users = $total_users - $admin_users;
                ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total utilisateurs</span>
                    <strong><?php echo $total_users; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Administrateurs</span>
                    <strong class="text-danger"><?php echo $admin_users; ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Utilisateurs normaux</span>
                    <strong class="text-secondary"><?php echo $normal_users; ?></strong>
                </div>
            </div>

            <div class="card p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-id-card me-2 text-secondary"></i>Mon compte admin</h6>
                <div class="small text-muted mb-2">Prénom : <?php echo htmlspecialchars($current_admin['first_name'] ?? ''); ?></div>
                <div class="small text-muted mb-2">Nom : <?php echo htmlspecialchars($current_admin['last_name'] ?? ''); ?></div>
                <div class="small text-muted mb-3">Identifiant : <?php echo htmlspecialchars($current_admin['username'] ?? $_SESSION['username']); ?></div>
                <a
                    href="admin.php?edit_self=1#self-edit-form"
                    class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-pen me-1"></i>Modifier mes identifiants
                </a>
            </div>

            <?php if ($show_self_edit): ?>
            <div class="card p-4" id="self-edit-form">
                <h6 class="fw-bold mb-3"><i class="fas fa-user-pen me-2 text-primary"></i>Modifier mon compte</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="edit_user_id" value="<?php echo $current_admin_id; ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Prénom</label>
                        <input type="text" class="form-control form-control-sm" name="self_first_name" value="<?php echo htmlspecialchars($current_admin['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom</label>
                        <input type="text" class="form-control form-control-sm" name="self_last_name" value="<?php echo htmlspecialchars($current_admin['last_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nom d'utilisateur</label>
                        <input type="text" class="form-control form-control-sm" name="self_username" value="<?php echo htmlspecialchars($current_admin['username'] ?? $_SESSION['username']); ?>" minlength="3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nouveau mot de passe</label>
                        <input type="password" class="form-control form-control-sm" name="self_password" minlength="8" placeholder="Laisser vide pour conserver l'actuel">
                    </div>
                    <button type="submit" name="update_self_account" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-save me-1"></i>Enregistrer mes modifications
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="col-md-7">
            <div class="card p-4">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-users me-2 text-primary"></i>
                    Utilisateurs (<?php echo count($users); ?>)
                </h6>

                <?php if (empty($users)): ?>
                    <p class="text-muted">Aucun utilisateur.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nom complet</th>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Créé le</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): $is_me = ($u['id'] === $current_admin_id); ?>
                        <tr <?php echo $is_me ? 'class="table-primary"' : ''; ?>>
                            <td><span class="fw-semibold"><?php echo htmlspecialchars(getUserDisplayName($u)); ?></span></td>
                            <td>
                                <span class="fw-semibold"><?php echo htmlspecialchars($u['username']); ?></span>
                                <?php if ($is_me): ?><small class="text-muted ms-1">(vous)</small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                    <span class="badge badge-admin"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-user">User</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></small></td>
                            <td class="text-end">
                                <?php if (!$is_me): ?>
                                <div class="d-flex justify-content-end gap-1">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-action edit-user-trigger"
                                        onclick="openEditUserModal(this)"
                                        data-id="<?php echo $u['id']; ?>"
                                        data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                        data-first-name="<?php echo htmlspecialchars($u['first_name'] ?? ''); ?>"
                                        data-last-name="<?php echo htmlspecialchars($u['last_name'] ?? ''); ?>"
                                        data-is-admin="<?php echo (int)$u['is_admin']; ?>"
                                        title="Modifier">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <!-- Voir le compte de l'utilisateur -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="impersonate_user" value="<?php echo $u['id']; ?>">
                                        <button class="btn btn-outline-primary btn-action" title="Consulter son app">
                                            <i class="fas fa-eye me-1"></i>Voir
                                        </button>
                                    </form>
                                    <!-- Supprimer -->
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Supprimer « <?php echo htmlspecialchars($u['username']); ?> » et toutes ses données ? Cette action est irréversible.')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                        <input type="hidden" name="delete_user" value="<?php echo $u['id']; ?>">
                                        <button class="btn btn-outline-danger btn-action">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0" style="border-radius:16px;">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier le compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="edit_user_id" id="editUserId">

                    <div class="mb-3">
                        <label class="form-label">Prénom</label>
                        <input type="text" class="form-control" name="edit_first_name" id="editFirstName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" class="form-control" name="edit_last_name" id="editLastName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" name="edit_username" id="editUsername" minlength="3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" class="form-control" name="edit_password" id="editPassword" minlength="8" placeholder="Laisser vide pour conserver l'actuel">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="edit_is_admin" id="editIsAdmin">
                        <label class="form-check-label" for="editIsAdmin">Accès administrateur</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="update_user" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleNewPwd() {
    const input = document.getElementById('newPassword');
    const icon  = document.getElementById('newPwdIcon');
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
    if (!pwd) { msg.textContent = ''; return; }

    let score = 0;
    if (pwd.length >= 8)  score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;

    const levels = [
        { text: 'Très faible',  color: '#DC2626' },
        { text: 'Faible',       color: '#F97316' },
        { text: 'Moyen',        color: '#EAB308' },
        { text: 'Fort',         color: '#22C55E' },
        { text: 'Très fort',    color: '#16A34A' },
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
    if (!editUserModal || !button) return false;
    fillEditUserModal(button);
    const modal = bootstrap.Modal.getOrCreateInstance(editUserModal);
    modal.show();
    return false;
}
</script>
</body>
</html>
