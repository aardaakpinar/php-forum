<?php
declare(strict_types=1);

abstract class Controller
{
    /** O an giris yapmis kullaniciyi dondurur, yoksa null. */
    protected function currentUser(): ?array
    {
        return current_user();
    }

    /** Giris yapilmamissa login sayfasina yonlendirir, yapilmissa kullaniciyi dondurur. */
    protected function requireLogin(): array
    {
        return require_login();
    }

    /** POST isteklerinde CSRF token dogrulamasi yapar. */
    protected function verifyCsrf(): void
    {
        verify_csrf();
    }

    /** Suanki istek POST mu? */
    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** action=... alaninin degerini dondurur (POST veya GET). */
    protected function inputAction(): string
    {
        return (string)($_POST['action'] ?? $_GET['action'] ?? '');
    }

    protected function redirect(string $path): void
    {
        redirect($path);
    }

    protected function notFound(string $message = 'Not found.'): never
    {
        http_response_code(404);
        die($message);
    }

    protected function forbidden(string $message = 'Forbidden.'): never
    {
        http_response_code(403);
        die($message);
    }

    protected function badRequest(string $message = 'Invalid request.'): never
    {
        http_response_code(400);
        die($message);
    }

    /** Kullanicinin belirtilen kaydin sahibi ya da admin olup olmadigini kontrol eder. */
    protected function ownsOrAdmin(array $user, int $ownerId): bool
    {
        return (int)$user['id'] === $ownerId || ($user['role'] ?? '') === 'admin';
    }

    protected function isAdmin(?array $user): bool
    {
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }
}
