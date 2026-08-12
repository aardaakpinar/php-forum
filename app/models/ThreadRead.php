<?php
declare(strict_types=1);

final class ThreadRead
{
    public static function markRead(int $userId, int $threadId): void
    {
        $stmt = Database::get()->prepare('
            INSERT INTO thread_reads (user_id, thread_id, last_read_at) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE last_read_at = VALUES(last_read_at)
        ');
        $stmt->execute([$userId, $threadId, time()]);
    }
}
