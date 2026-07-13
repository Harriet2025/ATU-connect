<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// COURSE MODEL
// =========================================================
class Course extends BaseModel
{
    protected static string $table = 'courses';
    protected static string $primaryKey = 'course_id';

    public static function forLecturer(int $lecturerId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM courses WHERE lecturer_id = :lecturer_id ORDER BY course_name');
        $stmt->execute(['lecturer_id' => $lecturerId]);
        return $stmt->fetchAll();
    }

    public static function forStudent(int $studentId): array
    {
        $sql = 'SELECT c.* FROM courses c
                JOIN course_registrations cr ON cr.course_id = c.course_id
                WHERE cr.student_id = :student_id AND cr.status = :status';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['student_id' => $studentId, 'status' => 'active']);
        return $stmt->fetchAll();
    }

    // Joins lecturer display name + per-course assignment count for the
    // course grid (courses.html) — follows the same join-and-count pattern
    // as Announcement::feed().
    public static function feed(): array
    {
        $sql = 'SELECT c.*, u.full_name AS lecturer_name,
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id) AS assignment_count,
                        (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id AND cr.status = "active") AS student_count
                 FROM courses c
                 LEFT JOIN lecturers l ON l.lecturer_id = c.lecturer_id
                 LEFT JOIN users u ON u.user_id = l.user_id
                 ORDER BY c.course_name';
        return self::db()->query($sql)->fetchAll();
    }
}
