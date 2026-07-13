<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// ASSIGNMENT SUBMISSION MODEL
// =========================================================
class AssignmentSubmission extends BaseModel
{
    protected static string $table = 'assignment_submissions';
    protected static string $primaryKey = 'submission_id';

    public static function forAssignment(int $assignmentId): array
    {
        $sql = 'SELECT s.*, u.full_name AS student_name, u.identifier AS student_identifier
                FROM assignment_submissions s
                JOIN students st ON st.student_id = s.student_id
                JOIN users u ON u.user_id = st.user_id
                WHERE s.assignment_id = :aid
                ORDER BY s.submitted_at DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['aid' => $assignmentId]);
        return $stmt->fetchAll();
    }

    public static function forStudent(int $studentId, int $assignmentId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM assignment_submissions WHERE student_id = :sid AND assignment_id = :aid LIMIT 1');
        $stmt->execute(['sid' => $studentId, 'aid' => $assignmentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function recentForLecturer(int $lecturerId, int $limit = 10): array
    {
        $sql = 'SELECT s.*, u.full_name AS student_name, a.title AS assignment_title
                FROM assignment_submissions s
                JOIN assignments a ON a.assignment_id = s.assignment_id
                JOIN courses c ON c.course_id = a.course_id
                JOIN students st ON st.student_id = s.student_id
                JOIN users u ON u.user_id = st.user_id
                WHERE c.lecturer_id = :lecturer_id
                ORDER BY s.submitted_at DESC
                LIMIT ' . (int) $limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['lecturer_id' => $lecturerId]);
        return $stmt->fetchAll();
    }
}
