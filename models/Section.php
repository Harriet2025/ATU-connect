<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// SECTION MODEL — group channels scoped to a course, a
// department, or the whole school. Membership is virtual
// (derived from scope), so there is no members table.
// =========================================================
class Section extends BaseModel
{
    protected static string $table = 'sections';
    protected static string $primaryKey = 'section_id';

    public static function findScoped(string $scopeType, ?int $scopeId): ?array
    {
        if ($scopeType === 'school') {
            $stmt = self::db()->prepare("SELECT * FROM sections WHERE scope_type = 'school' LIMIT 1");
            $stmt->execute();
        } else {
            $stmt = self::db()->prepare('SELECT * FROM sections WHERE scope_type = :t AND scope_id = :id LIMIT 1');
            $stmt->execute(['t' => $scopeType, 'id' => $scopeId]);
        }
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // Find the section for a scope, creating it the first time it's needed.
    public static function ensure(string $scopeType, ?int $scopeId, string $name, int $createdBy): array
    {
        $existing = self::findScoped($scopeType, $scopeId);
        if ($existing) {
            return $existing;
        }
        $id = self::create([
            'scope_type' => $scopeType,
            'scope_id'   => $scopeId,
            'name'       => $name,
            'created_by' => $createdBy,
        ]);
        return self::find($id) ?? [];
    }

    // Latest message preview for a section (for the channel list).
    public static function lastMessage(int $sectionId): ?array
    {
        $sql = 'SELECT sm.body, sm.created_at, u.full_name AS sender_name
                FROM section_messages sm
                JOIN users u ON u.user_id = sm.sender_id
                WHERE sm.section_id = :id
                ORDER BY sm.created_at DESC LIMIT 1';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['id' => $sectionId]);
        return $stmt->fetch() ?: null;
    }
}
