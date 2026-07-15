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

    // Every assignment on a course the given student is actively enrolled on,
    // enriched with course code/name for the student's "My Assignments" view.
    public static function forStudentEnrolled(int $studentId): array
    {
        $sql = 'SELECT a.*, c.course_name, c.course_code FROM assignments a
                JOIN courses c ON c.course_id = a.course_id
                JOIN course_registrations cr ON cr.course_id = a.course_id AND cr.status = "active"
                WHERE cr.student_id = :sid
                ORDER BY a.due_date DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }

    // Every assignment on any course the given lecturer teaches, enriched
    // with course info plus how many enrolled students have / have not
    // submitted yet — powers the lecturer's "Manage" view.
    public static function forLecturerWithStats(int $lecturerId): array
    {
        $sql = 'SELECT a.*, c.course_name, c.course_code,
                       (SELECT COUNT(*) FROM course_registrations cr
                          WHERE cr.course_id = a.course_id AND cr.status = "active") AS enrolled_count,
                       (SELECT COUNT(*) FROM assignment_submissions s
                          WHERE s.assignment_id = a.assignment_id) AS submitted_count
                FROM assignments a
                JOIN courses c ON c.course_id = a.course_id
                WHERE c.lecturer_id = :lid
                ORDER BY a.due_date DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['lid' => $lecturerId]);
        return $stmt->fetchAll();
    }

    // Every assignment across the whole school, enriched the same way as the
    // lecturer view — powers the HOD/admin oversight of all lecturers' work.
    public static function allWithStats(): array
    {
        $sql = 'SELECT a.*, c.course_name, c.course_code,
                       u.full_name AS lecturer_name,
                       (SELECT COUNT(*) FROM course_registrations cr
                          WHERE cr.course_id = a.course_id AND cr.status = "active") AS enrolled_count,
                       (SELECT COUNT(*) FROM assignment_submissions s
                          WHERE s.assignment_id = a.assignment_id) AS submitted_count
                FROM assignments a
                JOIN courses c ON c.course_id = a.course_id
                LEFT JOIN lecturers l ON l.lecturer_id = c.lecturer_id
                LEFT JOIN users u ON u.user_id = l.user_id
                ORDER BY a.due_date DESC';
        return self::db()->query($sql)->fetchAll();
    }
}
