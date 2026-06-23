<?php

namespace App\Services;

use App\Repositories\MetaRepository;
use App\Repositories\UserRepository;

class AdminService
{
    private UserRepository $users;
    private MetaRepository $meta;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->meta = new MetaRepository();
    }

    public function handle(array $post, array &$session, int $currentAdminId): array
    {
        $state = [
            'error' => null,
            'success' => null,
            'redirect' => null,
            'show_self_edit' => false,
        ];

        if (isset($post['logout_action'])) {
            logout();
            $state['redirect'] = 'login.php';
            return $state;
        }

        if (isset($post['stop_impersonate'])) {
            unset($session['impersonate_user_id']);
            $state['redirect'] = 'admin.php';
            return $state;
        }

        if (isset($post['update_telegram'])) {
            return $this->handleTelegramUpdate($post);
        }

        if (isset($post['update_self_account'])) {
            return $this->handleSelfAccountUpdate($post, $session, $currentAdminId);
        }

        if (isset($post['create_user'])) {
            return $this->handleCreateUser($post);
        }

        if (isset($post['update_user'])) {
            return $this->handleUpdateUser($post, $session, $currentAdminId);
        }

        if (isset($post['delete_user'])) {
            return $this->handleDeleteUser($post, $currentAdminId);
        }

        if (isset($post['impersonate_user'])) {
            $uid = (int)($post['impersonate_user'] ?? 0);
            if ($uid > 0 && $uid !== $currentAdminId) {
                $session['impersonate_user_id'] = $uid;
                $state['redirect'] = 'index.php';
            }
        }

        return $state;
    }

    public function buildPageData(int $currentAdminId): array
    {
        $currentAdmin = $this->users->findById($currentAdminId);
        $users = $this->users->all();
        $totalUsers = count($users);
        $adminUsers = $this->users->countAdmins();

        return [
            'current_admin' => $currentAdmin,
            'users' => $users,
            'stats' => [
                'total_users' => $totalUsers,
                'admin_users' => $adminUsers,
                'normal_users' => max(0, $totalUsers - $adminUsers),
            ],
        ];
    }

    public function telegramTokenForUser(int $userId): string
    {
        return $this->meta->getForUser('telegram_bot_token', $userId);
    }

    public function telegramChatIdForUser(int $userId): string
    {
        return $this->meta->getForUser('telegram_chat_id', $userId);
    }

    private function handleTelegramUpdate(array $post): array
    {
        $uid = (int)($post['telegram_user_id'] ?? 0);
        $botToken = trim((string)($post['telegram_bot_token'] ?? ''));
        $chatId = trim((string)($post['telegram_chat_id'] ?? ''));

        if ($uid <= 0) {
            return $this->state(error: 'Utilisateur Telegram invalide.');
        }

        $this->meta->setForUser('telegram_bot_token', $botToken, $uid);
        $this->meta->setForUser('telegram_chat_id', $chatId, $uid);

        $success = 'Configuration Telegram mise à jour.';
        if ($botToken !== '' && $chatId !== '') {
            require_once dirname(__DIR__, 2) . '/telegram_bot.php';
            $sent = isset($__nikolaii) && $__nikolaii->sendMessage(
                '✅ MoneyMinder connecté ! Nikolaii est prêt à te surveiller 🐀',
                $uid
            );

            if ($sent) {
                $success .= ' Message de test envoyé !';
            } else {
                $success .= ' ⚠️ Échec du message de test — vérifiez le token et le chat_id.';
            }
        }

        return $this->state(success: $success);
    }

    private function handleSelfAccountUpdate(array $post, array &$session, int $currentAdminId): array
    {
        $username = trim((string)($post['self_username'] ?? ''));
        $firstName = trim((string)($post['self_first_name'] ?? ''));
        $lastName = trim((string)($post['self_last_name'] ?? ''));
        $password = (string)($post['self_password'] ?? '');

        $validationError = $this->validateAccountPayload($username, $firstName, $lastName, $password, true);
        if ($validationError !== null) {
            return $this->state(error: $validationError, showSelfEdit: true);
        }

        $fields = [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_admin' => 1,
        ];

        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (!$this->users->update($currentAdminId, $fields)) {
            return $this->state(
                error: 'Impossible de mettre à jour votre compte. Le nom d\'utilisateur existe peut-être déjà.',
                showSelfEdit: true
            );
        }

        $session['username'] = $username;
        $session['first_name'] = $firstName;
        $session['last_name'] = $lastName;
        $session['is_admin'] = true;

        return $this->state(redirect: 'admin.php?edit_self=1&self_updated=1#self-edit-form');
    }

    private function handleCreateUser(array $post): array
    {
        $username = trim((string)($post['new_username'] ?? ''));
        $firstName = trim((string)($post['new_first_name'] ?? ''));
        $lastName = trim((string)($post['new_last_name'] ?? ''));
        $password = (string)($post['new_password'] ?? '');
        $isAdmin = isset($post['new_is_admin']);

        $validationError = $this->validateAccountPayload($username, $firstName, $lastName, $password, false);
        if ($validationError !== null) {
            return $this->state(error: $validationError);
        }

        $userId = $this->users->create(
            $username,
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            $isAdmin,
            $firstName,
            $lastName
        );

        if ($userId === false) {
            return $this->state(error: 'Ce nom d\'utilisateur existe déjà.');
        }

        return $this->state(success: 'Compte "' . $username . '" créé avec succès.');
    }

    private function handleUpdateUser(array $post, array &$session, int $currentAdminId): array
    {
        $userId = (int)($post['edit_user_id'] ?? 0);
        $username = trim((string)($post['edit_username'] ?? ''));
        $firstName = trim((string)($post['edit_first_name'] ?? ''));
        $lastName = trim((string)($post['edit_last_name'] ?? ''));
        $password = (string)($post['edit_password'] ?? '');
        $isAdmin = isset($post['edit_is_admin']);

        if ($userId <= 0) {
            return $this->state(error: 'Utilisateur invalide.');
        }

        $validationError = $this->validateAccountPayload($username, $firstName, $lastName, $password, true);
        if ($validationError !== null) {
            return $this->state(error: $validationError);
        }

        if ($userId === $currentAdminId) {
            $isAdmin = true;
        } elseif (!$isAdmin && $this->users->countAdmins() <= 1) {
            return $this->state(error: 'Il faut conserver au moins un administrateur.');
        }

        $fields = [
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_admin' => $isAdmin ? 1 : 0,
        ];

        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (!$this->users->update($userId, $fields)) {
            return $this->state(error: 'Impossible de mettre à jour ce compte. Le nom d\'utilisateur existe peut-être déjà.');
        }

        if ($userId === $currentAdminId) {
            $session['username'] = $username;
            $session['first_name'] = $firstName;
            $session['last_name'] = $lastName;
            $session['is_admin'] = true;
        }

        return $this->state(success: 'Compte mis à jour avec succès.', showSelfEdit: $userId === $currentAdminId);
    }

    private function handleDeleteUser(array $post, int $currentAdminId): array
    {
        $userId = (int)($post['delete_user'] ?? 0);

        if ($userId === $currentAdminId) {
            return $this->state(error: 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($userId > 0) {
            $this->users->delete($userId);
        }

        return $this->state(success: 'Utilisateur supprimé avec toutes ses données.');
    }

    private function validateAccountPayload(
        string $username,
        string $firstName,
        string $lastName,
        string $password,
        bool $passwordOptional
    ): ?string {
        if (mb_strlen($username) < 3) {
            return 'Le nom d\'utilisateur doit faire au moins 3 caractères.';
        }

        if ($firstName === '' || $lastName === '') {
            return $passwordOptional
                ? 'Le nom et le prénom sont obligatoires.'
                : 'Veuillez renseigner le nom et le prénom.';
        }

        if ($password === '') {
            return $passwordOptional ? null : 'Le mot de passe doit faire au moins 8 caractères.';
        }

        if (mb_strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $passwordOptional
                ? 'Si renseigné, le mot de passe doit faire au moins 8 caractères et contenir une lettre et un chiffre.'
                : 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
        }

        return null;
    }

    private function state(
        ?string $error = null,
        ?string $success = null,
        ?string $redirect = null,
        bool $showSelfEdit = false
    ): array {
        return [
            'error' => $error,
            'success' => $success,
            'redirect' => $redirect,
            'show_self_edit' => $showSelfEdit,
        ];
    }
}
