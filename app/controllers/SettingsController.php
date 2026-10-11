<?php
declare(strict_types=1);

/**
 * SettingsController
 * Kullanıcı ayarları: profil, hesap (e-posta/şifre), görünüm, gezinme,
 * bildirimler, gizlilik, veri dışa aktarma ve hesap silme.
 */
final class SettingsController extends Controller
{
    /** Sekme => [etiket, ikon]. Şemadan gelen sekmeler ayrıca eklenir. */
    private function tabs(): array
    {
        $tabs = [
            'profile' => ['label' => 'Profile', 'icon' => 'user'],
            'account' => ['label' => 'Account', 'icon' => 'key-round'],
        ];
        foreach (user_settings_schema() as $id => $section) {
            $tabs[$id] = ['label' => $section['label'], 'icon' => $section['icon']];
        }
        $tabs['data'] = ['label' => 'Data & danger zone', 'icon' => 'database'];
        return $tabs;
    }

    public function index(): array
    {
        $user = $this->requireLogin();
        $tabs = $this->tabs();

        $tab = (string)($_GET['tab'] ?? 'profile');
        if (!isset($tabs[$tab])) {
            $tab = 'profile';
        }

        $errors = [];
        $success = null;

        if ($this->isPost()) {
            $this->verifyCsrf();
            $action = $this->inputAction();

            switch ($action) {
                case 'update_profile':
                    $tab = 'profile';
                    [$errors, $success] = $this->updateProfile($user);
                    break;
                case 'change_email':
                    $tab = 'account';
                    [$errors, $success] = $this->changeEmail($user);
                    break;
                case 'change_password':
                    $tab = 'account';
                    [$errors, $success] = $this->changePassword($user);
                    break;
                case 'save_prefs':
                    [$errors, $success, $tab] = $this->savePrefs($user, $tab);
                    break;
                case 'export_data':
                    $this->exportData($user);
                    break;
                case 'delete_account':
                    $tab = 'data';
                    $errors = $this->deleteAccount($user);
                    break;
            }
            $user = User::findPublic((int)$user['id']) ?? $user;
        }

        $schema = user_settings_schema();

        return [
            'user'    => $user,
            'tab'     => $tab,
            'tabs'    => $tabs,
            'schema'  => $schema,
            'prefs'   => user_prefs_for((int)$user['id']),
            'errors'  => $errors,
            'success' => $success,
        ];
    }

    /** @return array{0: array<int, string>, 1: ?string} */
    private function updateProfile(array $user): array
    {
        $bio      = trim((string)($_POST['bio'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $website  = trim((string)($_POST['website'] ?? ''));

        foreach ([validate_bio($bio), validate_location($location), validate_website($website)] as $err) {
            if ($err) {
                return [[$err], null];
            }
        }

        User::updateProfile((int)$user['id'], $bio, $location, $website);
        return [[], 'Your profile has been updated.'];
    }

    /** @return array{0: array<int, string>, 1: ?string} */
    private function changeEmail(array $user): array
    {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['current_password'] ?? '');
        $fullUser = User::findFullById((int)$user['id']);

        if (!$fullUser || !password_verify($password, $fullUser['password_hash'])) {
            usleep(300000);
            return [['Your current password is incorrect.'], null];
        }
        if ($err = validate_email($email)) {
            return [[$err], null];
        }
        if (strcasecmp($email, (string)$fullUser['email']) === 0) {
            return [['This is already your email address.'], null];
        }
        if (User::emailTaken($email, (int)$user['id'])) {
            return [['This email address is already registered.'], null];
        }

        User::updateEmail((int)$user['id'], $email);
        return [[], 'Your email address has been updated.'];
    }

    /** @return array{0: array<int, string>, 1: ?string} */
    private function changePassword(array $user): array
    {
        $errors = [];
        $success = null;

        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword     = (string)($_POST['new_password'] ?? '');
        $newPassword2    = (string)($_POST['new_password2'] ?? '');

        $fullUser = User::findFullById((int)$user['id']);

        if (!$fullUser || !password_verify($currentPassword, $fullUser['password_hash'])) {
            usleep(300000);
            $errors[] = 'Your current password is incorrect.';
        } elseif ($err = validate_password($newPassword)) {
            $errors[] = $err;
        } elseif ($newPassword !== $newPassword2) {
            $errors[] = 'The new passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            User::updatePassword((int)$user['id'], $hash);
            session_regenerate_id(true);
            $success = 'Your password has been updated.';
        }

        return [$errors, $success];
    }

    /** @return array{0: array<int, string>, 1: ?string, 2: string} */
    private function savePrefs(array $user, string $currentTab): array
    {
        $schema = user_settings_schema();
        $section = (string)($_POST['section'] ?? '');
        if (!isset($schema[$section])) {
            return [['Unknown settings section.'], null, $currentTab];
        }

        $errors = [];
        $input = is_array($_POST['prefs'] ?? null) ? $_POST['prefs'] : [];
        $values = settings_collect($schema[$section]['fields'], $input, $errors);
        if ($errors) {
            return [$errors, null, $section];
        }

        User::savePrefs((int)$user['id'], $values);
        return [[], $schema[$section]['label'] . ' settings saved.', $section];
    }

    /** Kullanıcının tüm verisini JSON olarak indirir (GDPR tarzı dışa aktarma). */
    private function exportData(array $user): never
    {
        $pdo = Database::get();
        $uid = (int)$user['id'];
        $fetch = static function (string $sql) use ($pdo, $uid): array {
            $st = $pdo->prepare($sql);
            $st->execute([$uid]);
            return $st->fetchAll();
        };

        $export = [
            'exported_at' => date('c'),
            'profile' => [
                'username'   => $user['username'],
                'email'      => $user['email'],
                'bio'        => $user['bio'],
                'location'   => $user['location'],
                'website'    => $user['website'],
                'role'       => $user['role'],
                'created_at' => date('c', (int)$user['created_at']),
            ],
            'preferences' => User::rawPrefs($uid),
            'threads' => $fetch('SELECT t.id, t.title, t.body, c.name AS category, t.created_at, t.edited_at
                                 FROM threads t LEFT JOIN categories c ON c.id = t.category_id
                                 WHERE t.user_id = ? ORDER BY t.id'),
            'replies' => $fetch('SELECT id, thread_id, body, created_at, edited_at FROM posts WHERE user_id = ? ORDER BY id'),
            'followed_threads' => array_column($fetch('SELECT thread_id FROM thread_follows WHERE user_id = ? ORDER BY thread_id'), 'thread_id'),
            'likes_given' => $fetch('SELECT target_type, target_id, created_at FROM likes WHERE user_id = ? ORDER BY created_at'),
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $user['username'] . '-data-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-store');
        echo $json;
        exit;
    }

    /** @return array<int, string> */
    private function deleteAccount(array $user): array
    {
        $errors = [];
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $fullUser = User::findFullById((int)$user['id']);

        if (!$fullUser || !password_verify($confirmPassword, $fullUser['password_hash'])) {
            usleep(300000);
            $errors[] = 'Incorrect password. Account was not deleted.';
            return $errors;
        }

        if (($fullUser['role'] ?? '') === 'admin' && User::countAdmins() <= 1) {
            return ['You are the only admin. Promote another member to admin before deleting your account.'];
        }

        User::delete((int)$user['id']);
        $_SESSION = [];
        session_destroy();
        $this->redirect('/');
    }
}
