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

<div class="modal fade" id="telegramModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#0088cc,#006699);">
                <h5 class="modal-title text-white fw-bold">
                    <i class="fab fa-telegram me-2"></i>Configuration Telegram
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="telegram_user_id" id="telegramUserId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <p class="text-muted small mb-3">
                    Utilisateur : <strong id="telegramUsername"></strong>
                </p>

                <div class="alert alert-info py-2" style="font-size:.85rem;">
                    <i class="fas fa-info-circle me-1"></i>
                    <strong>Comment obtenir le chat_id ?</strong><br>
                    1. Crée un bot via <a href="https://t.me/BotFather" target="_blank">@BotFather</a> → copie le token<br>
                    2. Envoie un message au bot depuis le compte Telegram de l'utilisateur<br>
                    3. Visite <code>https://api.telegram.org/bot<b>TOKEN</b>/getUpdates</code> → note le <code>chat.id</code>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Bot Token</label>
                    <input type="text" class="form-control form-control-sm font-monospace"
                           name="telegram_bot_token" id="telegramBotToken"
                           placeholder="123456789:AAF...">
                    <small class="text-muted">Fourni par @BotFather lors de la création du bot.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Chat ID</label>
                    <input type="text" class="form-control form-control-sm font-monospace"
                           name="telegram_chat_id" id="telegramChatId"
                           placeholder="123456789">
                    <small class="text-muted">ID du chat Telegram de l'utilisateur.</small>
                </div>

                <div id="telegramStatus" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="update_telegram" class="btn btn-info btn-sm text-white">
                    <i class="fab fa-telegram me-1"></i>Enregistrer & Tester
                </button>
            </div>
        </form>
    </div>
</div>
