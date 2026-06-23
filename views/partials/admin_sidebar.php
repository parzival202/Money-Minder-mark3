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
                        placeholder="********"
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

    <div class="card p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Statistiques</h6>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Total utilisateurs</span>
            <strong><?php echo (int)$stats['total_users']; ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Administrateurs</span>
            <strong class="text-danger"><?php echo (int)$stats['admin_users']; ?></strong>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-muted">Utilisateurs normaux</span>
            <strong class="text-secondary"><?php echo (int)$stats['normal_users']; ?></strong>
        </div>
    </div>

    <div class="card p-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-id-card me-2 text-secondary"></i>Mon compte admin</h6>
        <div class="small text-muted mb-2">Prénom : <?php echo htmlspecialchars($current_admin['first_name'] ?? ''); ?></div>
        <div class="small text-muted mb-2">Nom : <?php echo htmlspecialchars($current_admin['last_name'] ?? ''); ?></div>
        <div class="small text-muted mb-3">Identifiant : <?php echo htmlspecialchars($current_admin['username'] ?? $_SESSION['username']); ?></div>
        <a href="admin.php?edit_self=1#self-edit-form" class="btn btn-outline-secondary btn-sm w-100">
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
