<?php
declare(strict_types=1);

/**
 * SettingsController
 * Sifre degistirme ve hesap silme islemlerini yonetir.
 */
final class SettingsController extends Controller
{
    public function index(): array
    {
        $user = $this->requireLogin();
        $errors = [];
        $success = null;

        if ($this->isPost()) {
            $this->verifyCsrf();
            $action = $this->inputAction();

            if ($action === 'change_password') {
                [$errors, $success] = $this->changePassword($user);
            } elseif ($action === 'delete_account') {
                $errors = $this->deleteAccount($user);
            }
        }

        return [
            'user'    => $user,
            'errors'  => $errors,
            'success' => $success,
        ];
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

        User::delete((int)$user['id']);
        $_SESSION = [];
        session_destroy();
        $this->redirect('/');
    }
}
