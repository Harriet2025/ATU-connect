<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// MESSAGE MODEL
// =========================================================
class Message extends BaseModel
{
    protected static string $table = 'messages';
    protected static string $primaryKey = 'message_id';

    public static function forConversation(int $conversationId, int $limit = 50): array
    {
        $sql = 'SELECT m.*, u.full_name AS sender_name, u.profile_picture
                FROM messages m
                JOIN users u ON u.user_id = m.sender_id
                WHERE m.conversation_id = :cid AND m.is_deleted = 0
                ORDER BY m.sent_at DESC
                LIMIT ' . (int) $limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['cid' => $conversationId]);
        return array_reverse($stmt->fetchAll());
    }

    public static function countSentByUser(int $userId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) AS c FROM messages WHERE sender_id = :uid');
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetch()['c'];
    }
}
