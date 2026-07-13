<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// ANNOUNCEMENT ATTACHMENT MODEL
// =========================================================
class AnnouncementAttachment extends BaseModel
{
    protected static string $table = 'announcement_attachments';
    protected static string $primaryKey = 'attachment_id';

    public static function forAnnouncement(int $announcementId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM announcement_attachments WHERE announcement_id = :aid ORDER BY uploaded_at');
        $stmt->execute(['aid' => $announcementId]);
        return $stmt->fetchAll();
    }
}
