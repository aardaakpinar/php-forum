<?php
declare(strict_types=1);

/**
 * Database connection singleton.
 */
final class Database
{
    private static ?PDO $connection = null;

    private function __construct()
    {
        // Bu sinif ornek uretilmez (static kullanim icin).
    }

    public static function set(PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    public static function get(): PDO
    {
        if (self::$connection === null) {
            throw new RuntimeException('Database henuz baslatilmadi. config.php once yuklenmeli.');
        }
        return self::$connection;
    }
}
