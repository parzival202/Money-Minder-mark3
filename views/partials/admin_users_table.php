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
                <?php foreach ($users as $u): $is_me = ((int)$u['id'] === $current_admin_id); ?>
                <tr <?php echo $is_me ? 'class="table-primary"' : ''; ?>>
                    <td><span class="fw-semibold"><?php echo htmlspecialchars(getUserDisplayName($u)); ?></span></td>
                    <td>
                        <span class="fw-semibold"><?php echo htmlspecialchars($u['username']); ?></span>
                        <?php if ($is_me): ?><small class="text-muted ms-1">(vous)</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($u['is_admin'])): ?>
                            <span class="badge badge-admin"><i class="fas fa-shield-alt me-1"></i>Admin</span>
                        <?php else: ?>
                            <span class="badge badge-user">User</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></small></td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <?php if (!$is_me): ?>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-action"
                                    onclick="openEditUserModal(this)"
                                    data-id="<?php echo (int)$u['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                    data-first-name="<?php echo htmlspecialchars($u['first_name'] ?? ''); ?>"
                                    data-last-name="<?php echo htmlspecialchars($u['last_name'] ?? ''); ?>"
                                    data-is-admin="<?php echo (int)$u['is_admin']; ?>"
                                    title="Modifier">
                                <i class="fas fa-pen"></i>
                            </button>

                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="impersonate_user" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-outline-primary btn-action" title="Consulter son app">
                                    <i class="fas fa-eye me-1"></i>Voir
                                </button>
                            </form>
                            <?php endif; ?>

                            <button class="btn btn-outline-info btn-action"
                                    data-bs-toggle="modal"
                                    data-bs-target="#telegramModal"
                                    data-uid="<?php echo (int)$u['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                    data-token="<?php echo htmlspecialchars($adminService->telegramTokenForUser((int)$u['id'])); ?>"
                                    data-chatid="<?php echo htmlspecialchars($adminService->telegramChatIdForUser((int)$u['id'])); ?>"
                                    title="Configurer Telegram">
                                <i class="fab fa-telegram"></i>
                            </button>

                            <?php if (!$is_me): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Supprimer « <?php echo htmlspecialchars($u['username']); ?> » et toutes ses données ?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="delete_user" value="<?php echo (int)$u['id']; ?>">
                                <button class="btn btn-outline-danger btn-action">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
