<?php
declare(strict_types=1);

final class Thread
{
    /** İzin verilen sıralamalar => ORDER BY ifadesi (whitelist, kullanıcı girdisi SQL'e girmez). */
    private const SORTS = [
        'latest' => 'last_activity_at DESC, t.id DESC',
        'new'    => 't.created_at DESC, t.id DESC',
        'top'    => 'reply_count DESC, last_activity_at DESC',
        'views'  => 't.views DESC, last_activity_at DESC',
    ];

    public static function sortKeys(): array
    {
        return array_keys(self::SORTS);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('
            SELECT t.id, t.locked, t.pinned, t.user_id, t.title, t.body, t.created_at, t.edited_at,
                   t.views, t.category_id, u.username, u.role AS user_role, u.created_at AS user_joined,
                   c.name AS category_name, c.slug AS category_slug
            FROM threads t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN categories c ON c.id = t.category_id
            WHERE t.id = ?
        ');
        $stmt->execute([$id]);
        $thread = $stmt->fetch();
        return $thread ?: null;
    }

    /**
     * Sayfalı konu listesi.
     *
     * $opts: viewer_id, category_id, user_id, follower_id, query, sort, unanswered, page, per_page
     *
     * @return array{rows: array<int, array>, total: int, page: int, pages: int}
     */
    public static function paginate(array $opts): array
    {
        $viewerId = (int)($opts['viewer_id'] ?? 0);
        $perPage  = max(1, (int)($opts['per_page'] ?? effective_setting('threads_per_page')));
        $page     = max(1, (int)($opts['page'] ?? 1));
        $sort     = self::SORTS[$opts['sort'] ?? 'latest'] ?? self::SORTS['latest'];

        $where = [];
        $whereParams = [];
        $join = '';
        $joinParams = [];

        if (!empty($opts['category_id'])) {
            $where[] = 't.category_id = ?';
            $whereParams[] = (int)$opts['category_id'];
        }
        if (!empty($opts['user_id'])) {
            $where[] = 't.user_id = ?';
            $whereParams[] = (int)$opts['user_id'];
        }
        if (!empty($opts['follower_id'])) {
            $join .= ' JOIN thread_follows tf ON tf.thread_id = t.id AND tf.user_id = ?';
            $joinParams[] = (int)$opts['follower_id'];
        }
        if (!empty($opts['unanswered'])) {
            $where[] = 'NOT EXISTS (SELECT 1 FROM posts px WHERE px.thread_id = t.id)';
        }
        if (isset($opts['query']) && $opts['query'] !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$opts['query']) . '%';
            $where[] = "(t.title LIKE ? ESCAPE '\\\\' OR t.body LIKE ? ESCAPE '\\\\'
                         OR EXISTS (SELECT 1 FROM posts pq WHERE pq.thread_id = t.id AND pq.body LIKE ? ESCAPE '\\\\'))";
            array_push($whereParams, $like, $like, $like);
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $pdo = Database::get();

        // Toplam
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM threads t' . $join . $whereSql);
        $countStmt->execute(array_merge($joinParams, $whereParams));
        $total = (int)$countStmt->fetchColumn();

        $pages = max(1, (int)ceil($total / $perPage));
        $page  = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        // Sabitlenmiş konular her zaman en üstte
        $pinFirst = 't.pinned DESC, ';

        $sql = "
            SELECT t.id, t.title, t.body, t.created_at, t.pinned, t.locked, t.views, t.category_id,
                   u.username, u.role AS user_role,
                   c.name AS category_name, c.slug AS category_slug,
                   (SELECT COUNT(*) FROM posts p WHERE p.thread_id = t.id) AS reply_count,
                   COALESCE((SELECT MAX(p2.created_at) FROM posts p2 WHERE p2.thread_id = t.id), t.created_at) AS last_activity_at,
                   COALESCE((SELECT u2.username FROM posts p3 JOIN users u2 ON u2.id = p3.user_id
                             WHERE p3.thread_id = t.id ORDER BY p3.created_at DESC, p3.id DESC LIMIT 1), u.username) AS last_username,
                   (SELECT COUNT(*) FROM likes l WHERE l.target_type = 'thread' AND l.target_id = t.id) AS like_count,
                   tr.last_read_at
            FROM threads t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN categories c ON c.id = t.category_id
            LEFT JOIN thread_reads tr ON tr.thread_id = t.id AND tr.user_id = ?
            $join
            $whereSql
            ORDER BY {$pinFirst}{$sort}
            LIMIT ? OFFSET ?
        ";
        $stmt = $pdo->prepare($sql);
        $i = 1;
        $stmt->bindValue($i++, $viewerId, PDO::PARAM_INT);
        foreach (array_merge($joinParams, $whereParams) as $p) {
            $stmt->bindValue($i++, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    public static function countByUser(int $userId): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) AS c FROM threads WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public static function countAll(): int
    {
        return (int)Database::get()->query('SELECT COUNT(*) FROM threads')->fetchColumn();
    }

    public static function create(int $userId, string $title, string $body, ?int $categoryId = null): int
    {
        $stmt = Database::get()->prepare(
            'INSERT INTO threads (user_id, title, body, created_at, category_id) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, $body, time(), $categoryId]);
        return (int)Database::get()->lastInsertId();
    }

    public static function update(int $id, string $title, string $body): void
    {
        $stmt = Database::get()->prepare('UPDATE threads SET title = ?, body = ?, edited_at = ? WHERE id = ?');
        $stmt->execute([$title, $body, time(), $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::get();
        // Beğeniler FK ile bağlı olmadığından elle temizlenir
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'post' AND target_id IN (SELECT id FROM posts WHERE thread_id = ?)")->execute([$id]);
        $pdo->prepare("DELETE FROM likes WHERE target_type = 'thread' AND target_id = ?")->execute([$id]);
        Post::deleteByThread($id);
        $pdo->prepare('DELETE FROM threads WHERE id = ?')->execute([$id]);
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

    public static function setCategory(int $id, ?int $categoryId): void
    {
        $stmt = Database::get()->prepare('UPDATE threads SET category_id = ? WHERE id = ?');
        $stmt->execute([$categoryId, $id]);
    }

    public static function incrementViews(int $id): void
    {
        Database::get()->prepare('UPDATE threads SET views = views + 1 WHERE id = ?')->execute([$id]);
    }

    /** Bir konunun son N sayfa için toplam cevap sayısı. */
    public static function replyCount(int $id): int
    {
        $stmt = Database::get()->prepare('SELECT COUNT(*) FROM posts WHERE thread_id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
}
