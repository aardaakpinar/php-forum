<?php
declare(strict_types=1);

final class Post
{
    public static function listByThread(int $threadId): array
    {
        $stmt = Database::get()->prepare('
            SELECT p.id, p.user_id, p.body, p.created_at, p.edited_at, u.username
            FROM posts p JOIN users u ON u.id = p.user_id
            WHERE p.thread_id = ?
            ORDER BY p.created_at ASC
        ');
        $stmt->execute([$threadId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        return $post ?: null;
    }

    public static function countByUser(int $userId): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) AS c FROM posts WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public static function create(int $threadId, int $userId, string $body): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO posts (thread_id, user_id, body, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$threadId, $userId, $body, time()]);
        return (int)Database::get()->lastInsertId();
    }

    public static function update(int $id, string $body): void
    {
        $stmt = Database::get()->prepare('UPDATE posts SET body = ?, edited_at = ? WHERE id = ?');
        $stmt->execute([$body, time(), $id]);
    }

    public static function delete(int $id): void
    {
        Database::get()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    public static function deleteByThread(int $threadId): void
    {
        Database::get()->prepare('DELETE FROM posts WHERE thread_id = ?')->execute([$threadId]);
    }
}
