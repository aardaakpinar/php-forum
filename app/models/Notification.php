<?php
declare(strict_types=1);

final class Notification
{
    public static function create(int $userId, string $type, int $actorUserId, ?int $threadId = null, ?int $postId = null): void
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO notifications (user_id, type, actor_user_id, thread_id, post_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)'
        );
        $stmt->execute([$userId, $type, $actorUserId, $threadId, $postId, time()]);
    }

    public static function unreadCount(?array $user): int
    {
        if (!$user) {
            return 0;
        }
        try {
            $stmt = Database::get()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$user['id']]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    public static function fetchForUser(array $user, int $limit = 200): array
    {
        try {
            $stmt = Database::get()->prepare(
                'SELECT n.*, u.username AS actor_username FROM notifications n
                 JOIN users u ON u.id = n.actor_user_id
                 WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT ?'
            );
            $stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    public static function markAllRead(array $user): void
    {
        try {
            $stmt = Database::get()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$user['id']]);
        } catch (PDOException $e) {
            error_log("Error occurred while marking notifications as read: " . $e->getMessage());
        }
    }
}
