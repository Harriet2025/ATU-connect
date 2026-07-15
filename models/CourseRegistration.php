<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// COURSE REGISTRATION MODEL — student <-> course enrollment
// =========================================================
class CourseRegistration extends BaseModel
{
    protected static string $table = 'course_registrations';
    protected static string $primaryKey = 'registration_id';

    public static function findByStudentAndCourse(int $studentId, int $courseId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM course_registrations WHERE student_id = :sid AND course_id = :cid LIMIT 1');
        $stmt->execute(['sid' => $studentId, 'cid' => $courseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countActiveForCourse(int $courseId): int
    {
        $stmt = self::db()->prepare("SELECT COUNT(*) AS c FROM course_registrations WHERE course_id = :cid AND status = 'active'");
        $stmt->execute(['cid' => $courseId]);
        return (int) $stmt->fetch()['c'];
    }

    // user_ids of every actively-enrolled student on a course — used to fan
    // out a lecturer's class broadcast (in-app + email + SMS per prefs).
    public static function studentUserIdsForCourse(int $courseId): array
    {
        $sql = "SELECT s.user_id
                FROM course_registrations cr
                JOIN students s ON s.student_id = cr.student_id
                WHERE cr.course_id = :cid AND cr.status = 'active'";
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['cid' => $courseId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    }
}
