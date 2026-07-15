<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// SECTION MESSAGE MODEL — posts within a group channel
// =========================================================
class SectionMessage extends BaseModel
{
    protected static string $table = 'section_messages';
    protected static string $primaryKey = 'message_id';

    public static function forSection(int $sectionId, int $limit = 100): array
    {
        $sql = 'SELECT sm.*, u.full_name AS sender_name, u.profile_picture,
                       r.role_name AS sender_role
                FROM section_messages sm
                JOIN users u ON u.user_id = sm.sender_id
                JOIN roles r ON r.role_id = u.role_id
                WHERE sm.section_id = :id
                ORDER BY sm.created_at ASC
                LIMIT ' . (int) $limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['id' => $sectionId]);
        return $stmt->fetchAll();
    }
}
