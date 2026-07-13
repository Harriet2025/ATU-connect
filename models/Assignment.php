<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// ASSIGNMENT MODEL
// =========================================================
class Assignment extends BaseModel
{
    protected static string $table = 'assignments';
    protected static string $primaryKey = 'assignment_id';

    public static function forCourse(int $courseId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM assignments WHERE course_id = :course_id ORDER BY due_date DESC');
        $stmt->execute(['course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public static function upcomingForStudent(int $studentId, int $limit = 5): array
    {
        $sql = 'SELECT a.*, c.course_name, c.course_code FROM assignments a
                JOIN courses c ON c.course_id = a.course_id
                JOIN course_registrations cr ON cr.course_id = a.course_id AND cr.status = "active"
                WHERE cr.student_id = :student_id AND a.due_date >= NOW()
                ORDER BY a.due_date ASC
                LIMIT ' . (int) $limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    }

    public static function authoredBy(int $userId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM assignments WHERE created_by = :uid ORDER BY due_date DESC');
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }
}
