<?php
declare(strict_types=1);

/**
 * AuthController
 * Giris, kayit ve cikis islemlerini yonetir.
 */
final class AuthController extends Controller
{
    public function login(): array
    {
        if ($this->currentUser()) {
            $this->redirect('/');
        }

        $errors = [];
        $username = '';

        if ($this->isPost()) {
            $this->verifyCsrf();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $user = User::findFullByUsername($username);

            if ($user && is_locked($user)) {
                $errors[] = 'Your account has been temporarily locked due to too many failed attempts. Please try again later.';
            } elseif ($user && password_verify($password, $user['password_hash'])) {
                User::resetFailedAttempts((int)$user['id']);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $this->redirect('/');
            } else {
                if ($user) {
                    User::recordFailedAttempt($user);
                }
                usleep(300000);
                $errors[] = 'Invalid username or password.';
            }
        }

        return [
            'errors'   => $errors,
            'username' => $username,
        ];
    }

    public function register(): array
    {
        if ($this->currentUser()) {
            $this->redirect('/');
        }

        $errors = [];
        $username = '';
        $email = '';

        if ($this->isPost()) {
            $this->verifyCsrf();

            $username  = trim((string)($_POST['username'] ?? ''));
            $email     = trim((string)($_POST['email'] ?? ''));
            $password  = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            if ($err = validate_username($username)) $errors[] = $err;
            if ($err = validate_email($email))       $errors[] = $err;
            if ($err = validate_password($password)) $errors[] = $err;
            if ($password !== $password2)            $errors[] = 'The passwords do not match.';

            if (!$errors && User::existsByUsernameOrEmail($username, $email)) {
                $errors[] = 'This username or email address is already registered.';
            }

            if (!$errors) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $newId = User::create($username, $email, $hash);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newId;
                    $this->redirect(url());
                } catch (PDOException $ex) {
                    error_log('Register insert error: ' . $ex->getMessage());
                    $errors[] = 'This username or email address is already registered.';
                }
            }
        }

        return [
            'errors'   => $errors,
            'username' => $username,
            'email'    => $email,
        ];
    }

    public function logout(): array
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
            $this->destroySession();
            $this->redirect('/');
        }

        return [];
    }

    private function destroySession(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
