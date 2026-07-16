<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// LECTURER MODEL — extends users with staff profile fields
// =========================================================
class Lecturer extends BaseModel
{
    protected static string $table = 'lecturers';
    protected static string $primaryKey = 'lecturer_id';

    public static function findByUserId(int $userId): ?array
    {
        $rows = self::where(['user_id' => $userId]);
        return $rows[0] ?? null;
    }

    // user_ids of every lecturer attached to a department — powers the
    // "Lecturers only" announcement audience (HOD broadcast to staff).
    public static function lecturerUserIdsInDepartment(int $departmentId): array
    {
        $sql = 'SELECT u.user_id FROM lecturers l
                JOIN users u ON u.user_id = l.user_id
                WHERE u.department_id = :dept';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['dept' => $departmentId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }

    // Approved lecturers only — powers the HOD's "assign lecturer" dropdown
    // when creating/editing a course.
    public static function approvedList(): array
    {
        $sql = "SELECT l.lecturer_id, u.full_name, u.identifier
                FROM lecturers l
                JOIN users u ON u.user_id = l.user_id
                WHERE u.approval_status = 'approved'
                ORDER BY u.full_name";
        return self::db()->query($sql)->fetchAll();
    }
}
