<?php
declare(strict_types=1);

// ---- Hata yönetimi: kullanıcıya hata detayı sızdırma, log'a yaz ----
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Yollar ----
define('BASE_DIR', __DIR__);
define('APP_DIR', BASE_DIR . '/app');

// ---- MySQL bağlantı bilgileri ----
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'forum');
define('DB_USER', getenv('DB_USER') ?: 'forum');
define('DB_PASS', getenv('DB_PASS') ?: 'change_this_password');

// ---- Güvenli session ayarları (session_start()'tan ÖNCE ayarlanmalı) ----
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

ini_set('session.use_strict_mode', '1');   // sunucunun üretmediği session id kabul edilmez
ini_set('session.use_only_cookies', '1');  // session id URL'de taşınamaz
ini_set('session.cookie_httponly', '1');   // JS ile cookie'ye erişim yok (XSS'ten çalınmayı zorlaştırır)
ini_set('session.cookie_samesite', 'Strict'); // CSRF'ye ek katman

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_name('FORUM_SESSID');
session_start();

// Session fixation'a karşı periyodik ve girişte session id yenileme
if (empty($_SESSION['_started'])) {
    $_SESSION['_started'] = time();
    session_regenerate_id(true);
} elseif (time() - $_SESSION['_started'] > 900) {
    session_regenerate_id(true);
    $_SESSION['_started'] = time();
}

// ---- Güvenlik başlıkları ----
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Referrer-Policy: no-referrer");
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header(
    "Content-Security-Policy: "
    ."default-src 'self'; "
    ."style-src 'self'; "
    ."script-src 'self'; "
    ."connect-src 'self'; "
    ."base-uri 'self'; "
    ."form-action 'self'; "
    ."frame-ancestors 'none'; "
    ."object-src 'none'"
);

if ($isHttps) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains');
}

// ---- Veritabanı bağlantısı ----
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    $initCommandKey = (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_INIT_COMMAND'))
        ? \Pdo\Mysql::ATTR_INIT_COMMAND
        : PDO::MYSQL_ATTR_INIT_COMMAND;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        $initCommandKey               => "SET NAMES utf8mb4, sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'",
    ]);
} catch (PDOException $ex) {
    error_log('DB connection error: ' . $ex->getMessage());
    http_response_code(500);
    die('Server error. Please try again later.');
}

// ---- MVC catirdisi: core, model ve controller siniflarini yukle ----
require __DIR__ . '/app/core/Database.php';
Database::set($pdo);

require __DIR__ . '/app/core/helpers.php';
require __DIR__ . '/app/core/Controller.php';

foreach (glob(__DIR__ . '/app/models/*.php') as $__modelFile) {
    require $__modelFile;
}
unset($__modelFile);

foreach (glob(__DIR__ . '/app/controllers/*.php') as $__controllerFile) {
    require $__controllerFile;
}
unset($__controllerFile);

require __DIR__ . '/app/core/Router.php';

// ---- Tabloları idempotent şekilde oluşturur (varsa eksik kolonları ekler) ----

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

// ---- Şema (idempotent, ama sadece gerektiğinde çalışır) ----

$__usersTableExists = (bool)$pdo->query(
    "SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' LIMIT 1"
)->fetchColumn();

if (!$__usersTableExists) {
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS thread_follows (
        user_id INT UNSIGNED NOT NULL,
        thread_id INT UNSIGNED NOT NULL,
        created_at INT NOT NULL,
        PRIMARY KEY (user_id, thread_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS thread_reads (
        user_id INT UNSIGNED NOT NULL,
        thread_id INT UNSIGNED NOT NULL,
        last_read_at INT NOT NULL,
        PRIMARY KEY (user_id, thread_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} else {
    ensure_column($pdo, 'threads', 'edited_at', 'edited_at INT NULL');
    ensure_column($pdo, 'posts', 'edited_at', 'edited_at INT NULL');
    ensure_column($pdo, 'threads', 'pinned', 'pinned TINYINT(1) NOT NULL DEFAULT 0');
}
unset($__usersTableExists);