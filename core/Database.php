<?php
namespace Core;

use PDO;
use PDOException;

// =========================================================
// DATABASE — singleton PDO wrapper (prepared statements only)
// =========================================================
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $cfg = DB_CONFIG;
            $dsn = "{$cfg['driver']}:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

            try {
                self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
                http_response_code(HTTP_SERVER_ERROR);
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
        }

        return self::$instance;
    }
}
