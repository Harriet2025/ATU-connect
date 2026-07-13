<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// DOCUMENT MODEL — notice board documents
// =========================================================
class Document extends BaseModel
{
    protected static string $table = 'documents';
    protected static string $primaryKey = 'document_id';

    // Joins uploader display name for the "posted by" line on notice-board.html.
    // Mirrors Announcement::feed()'s join pattern; filters applied by the caller
    // (DocumentController::index() already builds its own WHERE clause dynamically,
    // so this takes pre-built conditions/params to stay a single query).
    public static function feed(array $conditions = [], array $params = []): array
    {
        $sql = 'SELECT d.*, u.full_name AS uploader_name
                FROM documents d
                JOIN users u ON u.user_id = d.uploaded_by';
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY d.is_pinned DESC, d.uploaded_at DESC';

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
