<?php
declare(strict_types=1);

final class Category
{
    /** Tüm kategoriler + konu sayıları. */
    public static function all(): array
    {
        return Database::get()->query('
            SELECT c.id, c.name, c.slug, c.description, c.position,
                   (SELECT COUNT(*) FROM threads t WHERE t.category_id = c.id) AS thread_count
            FROM categories c
            ORDER BY c.position ASC, c.id ASC
        ')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::get()->prepare('SELECT * FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        $stmt = Database::get()->prepare('SELECT 1 FROM categories WHERE slug = ? AND id != ?');
        $stmt->execute([$slug, $exceptId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(string $name, string $slug, string $description): int
    {
        $pdo = Database::get();
        $pos = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')->fetchColumn();
        $pdo->prepare('INSERT INTO categories (name, slug, description, position) VALUES (?, ?, ?, ?)')
            ->execute([$name, $slug, $description, $pos]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, string $name, string $slug, string $description): void
    {
        Database::get()->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?')
            ->execute([$name, $slug, $description, $id]);
    }

    /** Kategoriyi siler; konuları $moveToId kategorisine taşır. */
    public static function delete(int $id, int $moveToId): void
    {
        $pdo = Database::get();
        $pdo->prepare('UPDATE threads SET category_id = ? WHERE category_id = ?')->execute([$moveToId, $id]);
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    }
}
