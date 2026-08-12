<?php
declare(strict_types=1);

final class ThreadFollow
{
    public static function isFollowing(int $userId, int $threadId): bool
    {
        $stmt = Database::get()->prepare('SELECT 1 FROM thread_follows WHERE user_id = ? AND thread_id = ?');
        $stmt->execute([$userId, $threadId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function follow(int $userId, int $threadId): void
    {
        $stmt = Database::get()->prepare(
            'INSERT IGNORE INTO thread_follows (user_id, thread_id, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $threadId, time()]);
    }

    public static function unfollow(int $userId, int $threadId): void
    {
        $stmt = Database::get()->prepare('DELETE FROM thread_follows WHERE user_id = ? AND thread_id = ?');
        $stmt->execute([$userId, $threadId]);
    }

    public static function followerIds(int $threadId, ?int $excludeUserId = null): array
    {
        if ($excludeUserId !== null) {
            $stmt = Database::get()->prepare('SELECT user_id FROM thread_follows WHERE thread_id = ? AND user_id != ?');
            $stmt->execute([$threadId, $excludeUserId]);
        } else {
            $stmt = Database::get()->prepare('SELECT user_id FROM thread_follows WHERE thread_id = ?');
            $stmt->execute([$threadId]);
        }
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }
}
