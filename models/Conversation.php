<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// CONVERSATION MODEL — direct + group messaging threads
// =========================================================
class Conversation extends BaseModel
{
    protected static string $table = 'conversations';
    protected static string $primaryKey = 'conversation_id';

    public static function findOrCreateDirect(int $userIdA, int $userIdB): int
    {
        $sql = 'SELECT cp1.conversation_id
                FROM conversation_participants cp1
                JOIN conversation_participants cp2 ON cp1.conversation_id = cp2.conversation_id
                JOIN conversations c ON c.conversation_id = cp1.conversation_id
                WHERE c.is_group = 0
                  AND cp1.user_id = :user_a
                  AND cp2.user_id = :user_b
                LIMIT 1';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['user_a' => $userIdA, 'user_b' => $userIdB]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['conversation_id'];
        }

        $db = self::db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO conversations (is_group, created_by) VALUES (0, :created_by)');
            $stmt->execute(['created_by' => $userIdA]);
            $conversationId = (int) $db->lastInsertId();

            $stmt = $db->prepare('INSERT INTO conversation_participants (conversation_id, user_id) VALUES (:cid, :uid)');
            $stmt->execute(['cid' => $conversationId, 'uid' => $userIdA]);
            $stmt->execute(['cid' => $conversationId, 'uid' => $userIdB]);

            $db->commit();
            return $conversationId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function forUser(int $userId): array
    {
        // For 1:1 (non-group) threads, resolve the OTHER participant's name
        // so the UI can show "Jane Wire" instead of "Direct Message #4".
        $sql = 'SELECT c.*,
                       (SELECT body FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
                       (SELECT sent_at FROM messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.sent_at DESC LIMIT 1) AS last_message_at,
                       cp.last_read_at,
                       (SELECT u.full_name FROM conversation_participants cp2
                          JOIN users u ON u.user_id = cp2.user_id
                          WHERE cp2.conversation_id = c.conversation_id AND cp2.user_id != :uid_name LIMIT 1) AS other_participant_name
                FROM conversations c
                JOIN conversation_participants cp ON cp.conversation_id = c.conversation_id AND cp.user_id = :uid
                ORDER BY last_message_at DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['uid' => $userId, 'uid_name' => $userId]);
        return $stmt->fetchAll();
    }

    public static function isParticipant(int $conversationId, int $userId): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid');
        $stmt->execute(['cid' => $conversationId, 'uid' => $userId]);
        return (bool) $stmt->fetch();
    }

    // Everyone in the conversation except the sender — used to fan out
    // "new message" notifications through the recipient's preferred channels.
    public static function otherParticipantIds(int $conversationId, int $excludeUserId): array
    {
        $stmt = self::db()->prepare('SELECT user_id FROM conversation_participants WHERE conversation_id = :cid AND user_id != :exclude_id');
        $stmt->execute(['cid' => $conversationId, 'exclude_id' => $excludeUserId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }
}
