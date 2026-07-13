<?php

require_once __DIR__ . '/Model.php';

class Submission extends Model
{
    public static function findByAssignmentAndStudent(int $assignmentId, int $studentId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1');
        $stmt->execute([$assignmentId, $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $assignmentId, int $studentId, string $filePath, bool $isLate): int
    {
        $db = self::db();
        $stmt = $db->prepare(
            'INSERT INTO submissions (assignment_id, student_id, file_path, is_late)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submitted_at = NOW(), is_late = VALUES(is_late), score = NULL, feedback = NULL'
        );
        $stmt->execute([$assignmentId, $studentId, $filePath, $isLate ? 1 : 0]);
        return (int)$db->lastInsertId();
    }

    public static function forAssignment(int $assignmentId): array
    {
        $stmt = self::db()->prepare(
            "SELECT s.*, u.full_name AS student_name, u.student_id AS student_index
             FROM submissions s JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ? ORDER BY s.submitted_at DESC"
        );
        $stmt->execute([$assignmentId]);
        return $stmt->fetchAll();
    }

    public static function grade(int $submissionId, float $score, string $feedback, int $graderId): void
    {
        self::db()->prepare(
            'UPDATE submissions SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW() WHERE id = ?'
        )->execute([$score, $feedback, $graderId, $submissionId]);
    }
}
