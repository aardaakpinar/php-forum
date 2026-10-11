<?php
declare(strict_types=1);

final class User
{
    public static function findPublic(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT id, username, email, role, bio, location, website, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findPublicByUsername(string $username): ?array
    {
        $stmt = Database::get()->prepare('SELECT id, username, role, bio, location, website, created_at FROM users WHERE username = ?');
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
        $pdo = Database::get();
        // İlk kayıtlı kullanıcı otomatik olarak yönetici olur
        $role = ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) ? 'admin' : 'member';
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $role, time()]);
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
        $maxAttempts = (int)site_setting('login_max_attempts');
        $lockedUntil = $attempts >= $maxAttempts ? time() + (int)site_setting('lockout_minutes') * 60 : 0;
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

    public static function updateBio(int $id, string $bio): void
    {
        Database::get()->prepare('UPDATE users SET bio = ? WHERE id = ?')->execute([$bio, $id]);
    }

    public static function updateProfile(int $id, string $bio, string $location, string $website): void
    {
        Database::get()->prepare('UPDATE users SET bio = ?, location = ?, website = ? WHERE id = ?')
            ->execute([$bio, $location, $website, $id]);
    }

    public static function emailTaken(string $email, int $exceptId): bool
    {
        $stmt = Database::get()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $exceptId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function updateEmail(int $id, string $email): void
    {
        Database::get()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$email, $id]);
    }

    /** Kullanıcının kayıtlı (ham) tercihleri: key => value. */
    public static function rawPrefs(int $id): array
    {
        try {
            $stmt = Database::get()->prepare('SELECT pref_key, pref_value FROM user_prefs WHERE user_id = ?');
            $stmt->execute([$id]);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['pref_key']] = $row['pref_value'];
            }
            return $out;
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @param array<string,string> $values */
    public static function savePrefs(int $id, array $values): void
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO user_prefs (user_id, pref_key, pref_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value)'
        );
        foreach ($values as $key => $value) {
            $stmt->execute([$id, $key, $value]);
        }
        user_prefs_for($id, true);
    }

    /** Kullanıcının son konu/cevap zamanı (flood kontrolü için); yoksa 0. */
    public static function lastPostAt(int $id): int
    {
        $stmt = Database::get()->prepare(
            'SELECT GREATEST(
                COALESCE((SELECT MAX(created_at) FROM threads WHERE user_id = ?), 0),
                COALESCE((SELECT MAX(created_at) FROM posts WHERE user_id = ?), 0)
             )'
        );
        $stmt->execute([$id, $id]);
        return (int)$stmt->fetchColumn();
    }

    public static function setRole(int $id, string $role): void
    {
        Database::get()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    }

    public static function countAll(): int
    {
        return (int)Database::get()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public static function newest(): ?array
    {
        $row = Database::get()->query('SELECT id, username, created_at FROM users ORDER BY id DESC LIMIT 1')->fetch();
        return $row ?: null;
    }

    public static function countAdmins(): int
    {
        return (int)Database::get()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }

    /** Yönetim paneli üye listesi (sayfalı). */
    public static function paginate(int $page, int $perPage = 25, string $q = ''): array
    {
        $pdo = Database::get();
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = " WHERE u.username LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\'";
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $params = [$like, $like];
        }
        $c = $pdo->prepare('SELECT COUNT(*) FROM users u' . $where);
        $c->execute($params);
        $total = (int)$c->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $stmt = $pdo->prepare('
            SELECT u.id, u.username, u.email, u.role, u.created_at,
                   (SELECT COUNT(*) FROM threads t WHERE t.user_id = u.id) AS thread_count,
                   (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS post_count
            FROM users u' . $where . ' ORDER BY u.id DESC LIMIT ? OFFSET ?');
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
