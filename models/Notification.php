<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// NOTIFICATION MODEL
// =========================================================
class Notification extends BaseModel
{
    protected static string $table = 'notifications';
    protected static string $primaryKey = 'notification_id';

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCountForUser(int $userId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetch()['c'];
    }

    public static function markAllRead(int $userId): bool
    {
        $stmt = self::db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        return $stmt->execute(['uid' => $userId]);
    }
}
