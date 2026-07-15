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

    // Creates a direct thread if none exists. The initiator's participant
    // row is 'accepted' (it's in their inbox immediately); the recipient's
    // is 'pending' — an unsolicited DM that sits in their "requests" tray
    // until they accept it. An already-existing thread is returned as-is
    // (its accept/pending states are preserved).
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

            $stmt = $db->prepare('INSERT INTO conversation_participants (conversation_id, user_id, status) VALUES (:cid, :uid, :status)');
            $stmt->execute(['cid' => $conversationId, 'uid' => $userIdA, 'status' => 'accepted']);
            $stmt->execute(['cid' => $conversationId, 'uid' => $userIdB, 'status' => 'pending']);

            $db->commit();
            return $conversationId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // $box: 'inbox' (accepted threads) or 'requests' (pending threads).
    // Each row carries unread_count (messages from others since this user
    // last read the thread) so the UI can badge conversations and total.
    public static function forUser(int $userId, string $box = 'inbox'): array
    {
        $status = $box === 'requests' ? 'pending' : 'accepted';
        $sql = 'SELECT c.*,
                       (SELECT body FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0 ORDER BY m.sent_at DESC LIMIT 1) AS last_message,
                       (SELECT sent_at FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0 ORDER BY m.sent_at DESC LIMIT 1) AS last_message_at,
                       cp.last_read_at,
                       cp.status AS my_status,
                       (SELECT COUNT(*) FROM messages m
                          WHERE m.conversation_id = c.conversation_id
                            AND m.sender_id != :uid_unread
                            AND m.is_deleted = 0
                            AND (cp.last_read_at IS NULL OR m.sent_at > cp.last_read_at)) AS unread_count,
                       (SELECT u.full_name FROM conversation_participants cp2
                          JOIN users u ON u.user_id = cp2.user_id
                          WHERE cp2.conversation_id = c.conversation_id AND cp2.user_id != :uid_name LIMIT 1) AS other_participant_name
                FROM conversations c
                JOIN conversation_participants cp ON cp.conversation_id = c.conversation_id AND cp.user_id = :uid
                WHERE cp.status = :status
                  AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id AND m.is_deleted = 0)
                ORDER BY last_message_at DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['uid' => $userId, 'uid_unread' => $userId, 'uid_name' => $userId, 'status' => $status]);
        return $stmt->fetchAll();
    }

    // Total unread across the user's inbox (accepted) threads + how many
    // pending requests are waiting — powers the nav badge + requests tab.
    public static function counts(int $userId): array
    {
        $sql = 'SELECT
                  COALESCE(SUM(CASE WHEN cp.status = "accepted" THEN (
                    SELECT COUNT(*) FROM messages m
                    WHERE m.conversation_id = cp.conversation_id
                      AND m.sender_id != :uid1 AND m.is_deleted = 0
                      AND (cp.last_read_at IS NULL OR m.sent_at > cp.last_read_at)
                  ) ELSE 0 END), 0) AS inbox_unread,
                  COALESCE(SUM(CASE WHEN cp.status = "pending"
                    AND EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = cp.conversation_id AND m.is_deleted = 0)
                    THEN 1 ELSE 0 END), 0) AS requests
                FROM conversation_participants cp
                WHERE cp.user_id = :uid2';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);
        $row = $stmt->fetch();
        return [
            'inbox_unread' => (int) ($row['inbox_unread'] ?? 0),
            'requests'     => (int) ($row['requests'] ?? 0),
        ];
    }

    public static function isParticipant(int $conversationId, int $userId): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid');
        $stmt->execute(['cid' => $conversationId, 'uid' => $userId]);
        return (bool) $stmt->fetch();
    }

    public static function participantStatus(int $conversationId, int $userId): ?string
    {
        $stmt = self::db()->prepare('SELECT status FROM conversation_participants WHERE conversation_id = :cid AND user_id = :uid');
        $stmt->execute(['cid' => $conversationId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row['status'] ?? null;
    }

    public static function setParticipantStatus(int $conversationId, int $userId, string $status): void
    {
        $stmt = self::db()->prepare('UPDATE conversation_participants SET status = :s WHERE conversation_id = :cid AND user_id = :uid');
        $stmt->execute(['s' => $status, 'cid' => $conversationId, 'uid' => $userId]);
    }

    // Stamp the thread read for this user (clears its unread badge).
    public static function markRead(int $conversationId, int $userId): void
    {
        $stmt = self::db()->prepare('UPDATE conversation_participants SET last_read_at = NOW() WHERE conversation_id = :cid AND user_id = :uid');
        $stmt->execute(['cid' => $conversationId, 'uid' => $userId]);
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
