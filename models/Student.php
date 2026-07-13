<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// STUDENT MODEL — extends users with academic profile fields
// =========================================================
class Student extends BaseModel
{
    protected static string $table = 'students';
    protected static string $primaryKey = 'student_id';

    public static function findByUserId(int $userId): ?array
    {
        $rows = self::where(['user_id' => $userId]);
        return $rows[0] ?? null;
    }

    public static function studentUserIdsInDepartment(int $departmentId): array
    {
        $sql = 'SELECT u.user_id FROM students s
                JOIN users u ON u.user_id = s.user_id
                WHERE u.department_id = :dept';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['dept' => $departmentId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }
}
