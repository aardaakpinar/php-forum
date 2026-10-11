<?php
declare(strict_types=1);

/**
 * Beğeniler (konu ve cevaplar için). target_type: 'thread' | 'post'
 */
final class Like
{
    private const TYPES = ['thread', 'post'];

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /** Beğeniyi açar/kapatır; yeni durumu (true = beğenildi) döndürür. */
    public static function toggle(int $userId, string $type, int $targetId): bool
    {
        $pdo = Database::get();
        $stmt = $pdo->prepare('SELECT 1 FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?');
        $stmt->execute([$userId, $type, $targetId]);

        if ($stmt->fetchColumn()) {
            $pdo->prepare('DELETE FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?')
                ->execute([$userId, $type, $targetId]);
            return false;
        }

        $pdo->prepare('INSERT INTO likes (user_id, target_type, target_id, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $type, $targetId, time()]);
        return true;
    }

    /**
     * @param int[] $ids
     * @return array<int, int> id => beğeni sayısı
     */
    public static function counts(string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            "SELECT target_id, COUNT(*) AS c FROM likes WHERE target_type = ? AND target_id IN ($in) GROUP BY target_id"
        );
        $stmt->execute(array_merge([$type], $ids));
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int)$r['target_id']] = (int)$r['c'];
        }
        return $out;
    }

    /**
     * @param int[] $ids
     * @return array<int, true> kullanıcının beğendiği id'ler
     */
    public static function likedBy(int $userId, string $type, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || $userId <= 0) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::get()->prepare(
            "SELECT target_id FROM likes WHERE user_id = ? AND target_type = ? AND target_id IN ($in)"
        );
        $stmt->execute(array_merge([$userId, $type], $ids));
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[(int)$r['target_id']] = true;
        }
        return $out;
    }
}
