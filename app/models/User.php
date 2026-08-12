<?php
declare(strict_types=1);

final class User
{
    public static function findPublic(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findPublicByUsername(string $username): ?array
    {
        $stmt = Database::get()->prepare('SELECT id, username, role, created_at FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findFullById(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findFullByUsername(string $username): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function existsByUsernameOrEmail(string $username, string $email): bool
    {
        $stmt = Database::get()->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        return (bool)$stmt->fetch();
    }

    public static function create(string $username, string $email, string $passwordHash): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO users (username, email, password_hash, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, time()]);
        return (int)Database::get()->lastInsertId();
    }

    public static function resetFailedAttempts(int $id): void
    {
        $stmt = Database::get()->prepare('UPDATE users SET failed_attempts = 0, locked_until = 0 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function recordFailedAttempt(array $user): void
    {
        $attempts = (int)$user['failed_attempts'] + 1;
        $lockedUntil = $attempts >= 5 ? time() + 900 : 0;
        $stmt = Database::get()->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
        $stmt->execute([$attempts, $lockedUntil, $user['id']]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = Database::get()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public static function idsByUsernames(array $usernames): array
    {
        if (!$usernames) {
            return [];
        }
        $in = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = Database::get()->prepare('SELECT id, username FROM users WHERE username IN (' . $in . ')');
        $stmt->execute(array_values($usernames));
        $rows = $stmt->fetchAll();

        $ids = [];
        foreach ($rows as $r) {
            $ids[(int)$r['id']] = $r['username'];
        }
        return array_keys($ids);
    }
}
