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

    // The semester course catalogue — code/name only, no auth required.
    // Powers the "which courses do you teach?" picker on the (pre-login)
    // registration form and the lecturer's "create assignment" course list.
    public static function catalog(): array
    {
        return self::db()
            ->query('SELECT course_id, course_code, course_name FROM courses ORDER BY course_name')
            ->fetchAll();
    }

    // A lecturer claims the catalogue courses they teach at sign-up. Only
    // claims courses that are currently unassigned (lecturer_id IS NULL) so
    // one lecturer can't yank another's course; the HOD can always reassign
    // afterwards from the dashboard.
    public static function claimCourses(int $lecturerId, array $courseIds): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        if (!$courseIds) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $sql = "UPDATE courses SET lecturer_id = ? WHERE lecturer_id IS NULL AND course_id IN ($placeholders)";
        $stmt = self::db()->prepare($sql);
        $stmt->execute(array_merge([$lecturerId], $courseIds));
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
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id AND a.due_date >= NOW()) AS due_count,
                        (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id AND cr.status = "active") AS student_count
                 FROM courses c
                 LEFT JOIN lecturers l ON l.lecturer_id = c.lecturer_id
                 LEFT JOIN users u ON u.user_id = l.user_id
                 ORDER BY c.course_name';
        return self::db()->query($sql)->fetchAll();
    }

    // Same shape as feed() but scoped to the courses one lecturer teaches —
    // powers the lecturer's "My Courses" grid + dashboard (they should only
    // ever see their own courses, not the whole catalogue).
    public static function feedForLecturer(int $lecturerId): array
    {
        $sql = 'SELECT c.*, u.full_name AS lecturer_name,
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id) AS assignment_count,
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id AND a.due_date >= NOW()) AS due_count,
                        (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id AND cr.status = "active") AS student_count
                 FROM courses c
                 LEFT JOIN lecturers l ON l.lecturer_id = c.lecturer_id
                 LEFT JOIN users u ON u.user_id = l.user_id
                 WHERE c.lecturer_id = :lid
                 ORDER BY c.course_name';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['lid' => $lecturerId]);
        return $stmt->fetchAll();
    }

    // Same shape as feed() but with an is_registered flag for one student,
    // so the "My Courses" grid can show Registered vs Register per card.
    public static function feedForStudent(int $studentId): array
    {
        $sql = 'SELECT c.*, u.full_name AS lecturer_name,
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id) AS assignment_count,
                        (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.course_id AND a.due_date >= NOW()) AS due_count,
                        (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id AND cr.status = "active") AS student_count,
                        EXISTS(SELECT 1 FROM course_registrations cr2
                                WHERE cr2.course_id = c.course_id AND cr2.student_id = :sid AND cr2.status = "active") AS is_registered
                 FROM courses c
                 LEFT JOIN lecturers l ON l.lecturer_id = c.lecturer_id
                 LEFT JOIN users u ON u.user_id = l.user_id
                 ORDER BY c.course_name';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['sid' => $studentId]);
        return $stmt->fetchAll();
    }
}
