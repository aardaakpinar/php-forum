<?php
declare(strict_types=1);

/**
 * Şema yönetimi.
 *
 * - İlk kurulumda temel tabloları oluşturur.
 * - Sonraki sürümlerde eksik kolon / tabloları ekler (schema_meta.version ile
 *   yalnızca gerektiğinde çalışır, her istekte ALTER denenmez).
 */

const FORUM_SCHEMA_VERSION = 4;

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

function forum_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function forum_schema_version(PDO $pdo): int
{
    try {
        $v = $pdo->query("SELECT v FROM schema_meta WHERE k = 'version'")->fetchColumn();
        return $v === false ? 0 : (int)$v;
    } catch (PDOException $e) {
        return 0;
    }
}

function forum_migrate(PDO $pdo): void
{
    if (forum_schema_version($pdo) >= FORUM_SCHEMA_VERSION) {
        return;
    }

    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    // ---- v1: temel tablolar ----
    if (!forum_table_exists($pdo, 'users')) {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(190) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'member',
            failed_attempts INT NOT NULL DEFAULT 0,
            locked_until INT NOT NULL DEFAULT 0,
            created_at INT NOT NULL
        ) $opts");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS threads (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            created_at INT NOT NULL,
            edited_at INT NULL,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            KEY idx_threads_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) $opts");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            thread_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at INT NOT NULL,
            edited_at INT NULL,
            KEY idx_posts_thread_id (thread_id),
            KEY idx_posts_user_id (user_id),
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) $opts");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(64) NOT NULL,
            actor_user_id INT UNSIGNED NOT NULL,
            thread_id INT UNSIGNED,
            post_id INT UNSIGNED,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at INT NOT NULL,
            KEY idx_notifications_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) $opts");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS thread_follows (
            user_id INT UNSIGNED NOT NULL,
            thread_id INT UNSIGNED NOT NULL,
            created_at INT NOT NULL,
            PRIMARY KEY (user_id, thread_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
        ) $opts");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS thread_reads (
            user_id INT UNSIGNED NOT NULL,
            thread_id INT UNSIGNED NOT NULL,
            last_read_at INT NOT NULL,
            PRIMARY KEY (user_id, thread_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
        ) $opts");
    } else {
        ensure_column($pdo, 'threads', 'edited_at', 'edited_at INT NULL');
        ensure_column($pdo, 'posts', 'edited_at', 'edited_at INT NULL');
        ensure_column($pdo, 'threads', 'pinned', 'pinned TINYINT(1) NOT NULL DEFAULT 0');
    }

    // ---- v2-v3: categories, likes, views, profile bios, and site settings ----
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        slug VARCHAR(60) NOT NULL UNIQUE,
        description VARCHAR(200) NOT NULL DEFAULT '',
        position INT NOT NULL DEFAULT 0
    ) $opts");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(32) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) $opts");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS likes (
        user_id INT UNSIGNED NOT NULL,
        target_type VARCHAR(10) NOT NULL,
        target_id INT UNSIGNED NOT NULL,
        created_at INT NOT NULL,
        PRIMARY KEY (user_id, target_type, target_id),
        KEY idx_likes_target (target_type, target_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    ensure_column($pdo, 'threads', 'category_id', 'category_id INT UNSIGNED NULL');
    ensure_column($pdo, 'threads', 'views', 'views INT UNSIGNED NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'bio', "bio VARCHAR(300) NOT NULL DEFAULT ''");

    // ---- v4: genişletilmiş ayarlar ----
    // Site ayarları: daha uzun anahtar/değer (duyuru, yasaklı kullanıcı adları vb.)
    $pdo->exec('ALTER TABLE site_settings MODIFY setting_key VARCHAR(64) NOT NULL');
    $pdo->exec('ALTER TABLE site_settings MODIFY setting_value TEXT NOT NULL');

    // Kullanıcı tercihleri (anahtar/değer)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_prefs (
        user_id INT UNSIGNED NOT NULL,
        pref_key VARCHAR(48) NOT NULL,
        pref_value VARCHAR(64) NOT NULL,
        PRIMARY KEY (user_id, pref_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) $opts");

    // Profil alanları
    ensure_column($pdo, 'users', 'location', "location VARCHAR(60) NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'website', "website VARCHAR(100) NOT NULL DEFAULT ''");

    // Varsayılan kategoriler (yalnızca hiç kategori yoksa)
    if ((int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $seed = [
            ['Announcements', 'announcements', 'Official news and updates from the team.', 1],
            ['General', 'general', 'Open discussion about anything.', 2],
            ['Help & Support', 'support', 'Ask questions and get answers from the community.', 3],
            ['Showcase', 'showcase', 'Share your projects and work.', 4],
            ['Feedback', 'feedback', 'Suggestions and bug reports for the forum itself.', 5],
        ];
        $ins = $pdo->prepare('INSERT INTO categories (name, slug, description, position) VALUES (?, ?, ?, ?)');
        foreach ($seed as $row) {
            $ins->execute($row);
        }
    }

    // Kategorisiz eski konuları "General" altına al
    $generalId = $pdo->query("SELECT id FROM categories WHERE slug = 'general'")->fetchColumn();
    if ($generalId === false) {
        $generalId = $pdo->query('SELECT id FROM categories ORDER BY position, id LIMIT 1')->fetchColumn();
    }
    if ($generalId !== false) {
        $upd = $pdo->prepare('UPDATE threads SET category_id = ? WHERE category_id IS NULL');
        $upd->execute([(int)$generalId]);
    }

    // ---- sürüm kaydı ----
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_meta (
        k VARCHAR(32) NOT NULL PRIMARY KEY,
        v VARCHAR(32) NOT NULL
    ) $opts");
    $stmt = $pdo->prepare(
        "INSERT INTO schema_meta (k, v) VALUES ('version', ?)
         ON DUPLICATE KEY UPDATE v = VALUES(v)"
    );
    $stmt->execute([(string)FORUM_SCHEMA_VERSION]);
}
