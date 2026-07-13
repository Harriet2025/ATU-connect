<?php

class Notifier
{
    public static function send(int $userId, string $type, string $message, ?int $referenceId = null): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $referenceId, $message]);
    }

    public static function sendToMany(array $userIds, string $type, string $message, ?int $referenceId = null): void
    {
        foreach (array_unique($userIds) as $userId) {
            self::send((int)$userId, $type, $message, $referenceId);
        }
    }

    public static function unreadCount(int $userId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
