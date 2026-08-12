<?php
declare(strict_types=1);

final class Thread
{
    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('
            SELECT t.id, t.locked, t.pinned, t.user_id, t.title, t.body, t.created_at, t.edited_at, u.username
            FROM threads t
            JOIN users u ON u.id = t.user_id
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        $thread = $stmt->fetch();
        return $thread ?: null;
    }

    public static function listAll(int $viewerId, int $limit = 100): array
    {
        $stmt = Database::get()->prepare('
            SELECT t.id, t.title, t.created_at, t.pinned, t.locked, u.username,
                   (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count,
                   COALESCE((SELECT MAX(p2.created_at) FROM posts p2 WHERE p2.thread_id = t.id), t.created_at) AS last_activity_at,
                   tr.last_read_at
            FROM threads t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN thread_reads tr ON tr.thread_id = t.id AND tr.user_id = ?
            ORDER BY t.pinned DESC, t.created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listByUser(int $userId, int $viewerId, int $limit = 30): array
    {
        $stmt = Database::get()->prepare('
            SELECT t.id, t.title, t.created_at,
                   COALESCE((SELECT MAX(p2.created_at) FROM posts p2 WHERE p2.thread_id = t.id), t.created_at) AS last_activity_at,
                   tr.last_read_at
            FROM threads t
            LEFT JOIN thread_reads tr ON tr.thread_id = t.id AND tr.user_id = ?
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function search(string $query, int $viewerId, int $limit = 100): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $stmt = Database::get()->prepare(
            "SELECT t.id, t.title, t.body, t.created_at, t.pinned, t.locked, u.username,
                    (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count,
                    COALESCE((SELECT MAX(p2.created_at) FROM posts p2 WHERE p2.thread_id = t.id), t.created_at) AS last_activity_at,
                    tr.last_read_at
             FROM threads t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN thread_reads tr ON tr.thread_id = t.id AND tr.user_id = ?
             WHERE t.title LIKE ? ESCAPE '\\\\' OR t.body LIKE ? ESCAPE '\\\\'
             ORDER BY t.pinned DESC, t.created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $like);
        $stmt->bindValue(3, $like);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countByUser(int $userId): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) AS c FROM threads WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public static function create(int $userId, string $title, string $body): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO threads (user_id, title, body, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, $body, time()]);
        return (int)Database::get()->lastInsertId();
    }

    public static function update(int $id, string $title, string $body): void
    {
        $stmt = Database::get()->prepare('UPDATE threads SET title = ?, body = ?, edited_at = ? WHERE id = ?');
        $stmt->execute([$title, $body, time(), $id]);
    }

    public static function delete(int $id): void
    {
        Post::deleteByThread($id);
        Database::get()->prepare('DELETE FROM threads WHERE id = ?')->execute([$id]);
    }

    public static function setLocked(int $id, bool $locked): void
    {
        $stmt = Database::get()->prepare('UPDATE threads SET locked = ? WHERE id = ?');
        $stmt->execute([$locked ? 1 : 0, $id]);
    }

    public static function setPinned(int $id, bool $pinned): void
    {
        $stmt = Database::get()->prepare('UPDATE threads SET pinned = ? WHERE id = ?');
        $stmt->execute([$pinned ? 1 : 0, $id]);
    }
}
