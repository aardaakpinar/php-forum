<?php
declare(strict_types=1);

final class Post
{
    /** Sayfa başına cevap sayısı (kullanıcı tercihi > site ayarı). */
    public static function perPage(): int
    {
        return max(1, (int)effective_setting('posts_per_page'));
    }

    /**
     * Konunun cevapları (sayfalı). Yazar bilgisi ve yazarın toplam mesaj sayısı dahil.
     *
     * @return array{rows: array<int, array>, total: int, page: int, pages: int}
     */
    public static function paginateByThread(int $threadId, int $page): array
    {
        $pdo = Database::get();
        $total = Thread::replyCount($threadId);
        $pages = max(1, (int)ceil($total / self::perPage()));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::perPage();

        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id, p.body, p.created_at, p.edited_at,
                   u.username, u.role AS user_role, u.created_at AS user_joined,
                   (SELECT COUNT(*) FROM posts pc WHERE pc.user_id = p.user_id) AS user_post_count
            FROM posts p JOIN users u ON u.id = p.user_id
            WHERE p.thread_id = ?
            ORDER BY p.created_at ASC, p.id ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $threadId, PDO::PARAM_INT);
        $stmt->bindValue(2, self::perPage(), PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages, 'offset' => $offset];
    }

    /** Bir postun hangi sayfada olduğunu döndürür (permalink için). */
    public static function pageOf(int $threadId, int $postId): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) FROM posts WHERE thread_id = ? AND id <= ?');
        $stmt->execute([$threadId, $postId]);
        $pos = max(1, (int)$stmt->fetchColumn());
        return (int)ceil($pos / self::perPage());
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

    public static function countAll(): int
    {
        return (int)Database::get()->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    }

    /** Kullanıcının son cevapları (profil sayfası). */
    public static function recentByUser(int $userId, int $limit = 15): array
    {
        $stmt = Database::get()->prepare('
            SELECT p.id, p.thread_id, p.body, p.created_at, t.title AS thread_title
            FROM posts p JOIN threads t ON t.id = p.thread_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
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
        $pdo = Database::get();
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'post' AND target_id = ?")->execute([$id]);
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }

    public static function deleteByThread(int $threadId): void
    {
        Database::get()->prepare('DELETE FROM posts WHERE thread_id = ?')->execute([$threadId]);
    }
}
