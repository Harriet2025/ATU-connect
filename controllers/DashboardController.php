<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Core\Database;
use Models\Announcement;
use Models\Assignment;
use Models\Notification;
use Models\Event;
use Models\Student;
use Models\Lecturer;
use Models\Course;
use Models\AssignmentSubmission;
use Models\Conversation;

// =========================================================
// DASHBOARD CONTROLLER — role-specific summary bundles
// =========================================================
class DashboardController extends BaseController
{
    public function student(Request $request): void
    {
        $this->requireRole([ROLE_STUDENT]);

        $student = Student::findByUserId(Auth::id());
        $studentId = $student['student_id'] ?? null;

        $unreadMessages = 0;
        if ($studentId) {
            $conversations = Conversation::forUser(Auth::id());
            foreach ($conversations as $c) {
                if ($c['last_message_at'] && (!$c['last_read_at'] || $c['last_message_at'] > $c['last_read_at'])) {
                    $unreadMessages++;
                }
            }
        }

        Response::success([
            'announcements'          => array_slice(Announcement::feed(), 0, 5),
            'upcoming_assignments'   => $studentId ? Assignment::upcomingForStudent((int) $studentId, 5) : [],
            'unread_notifications'   => Notification::unreadCountForUser(Auth::id()),
            'unread_messages'        => $unreadMessages,
            'upcoming_events'        => Event::upcoming(5),
        ]);
    }

    public function lecturer(Request $request): void
    {
        $this->requireRole([ROLE_LECTURER]);

        $lecturer = Lecturer::findByUserId(Auth::id());
        $lecturerId = $lecturer['lecturer_id'] ?? null;
        $courses = $lecturerId ? Course::forLecturer((int) $lecturerId) : [];

        $stats = [];
        foreach ($courses as $course) {
            $assignments = Assignment::forCourse((int) $course['course_id']);
            $registered = \Models\CourseRegistration::countActiveForCourse((int) $course['course_id']);
            foreach ($assignments as $assignment) {
                $submitted = count(AssignmentSubmission::forAssignment((int) $assignment['assignment_id']));
                $stats[] = [
                    'assignment_id' => $assignment['assignment_id'],
                    'title'         => $assignment['title'],
                    'course_code'   => $course['course_code'],
                    'submitted'     => $submitted,
                    'total'         => $registered,
                ];
            }
        }

        Response::success([
            'courses'             => $courses,
            'submission_stats'    => $stats,
            'recent_submissions'  => $lecturerId ? AssignmentSubmission::recentForLecturer((int) $lecturerId, 10) : [],
            'announcements'       => array_values(array_filter(Announcement::feed(), fn($a) => (int) $a['author_id'] === Auth::id())),
        ]);
    }

    public function admin(Request $request): void
    {
        $this->requireRole([ROLE_ADMIN]);

        $db = Database::connect();

        $count = fn(string $sql) => (int) $db->query($sql)->fetch()['c'];

        Response::success([
            'total_students'      => $count('SELECT COUNT(*) AS c FROM students'),
            'total_lecturers'     => $count('SELECT COUNT(*) AS c FROM lecturers'),
            'total_departments'   => $count('SELECT COUNT(*) AS c FROM departments'),
            'total_courses'       => $count('SELECT COUNT(*) AS c FROM courses'),
            'total_announcements' => $count('SELECT COUNT(*) AS c FROM announcements'),
            'total_assignments'   => $count('SELECT COUNT(*) AS c FROM assignments'),
            'total_messages'      => $count('SELECT COUNT(*) AS c FROM messages'),
            'active_users_today'  => $count("SELECT COUNT(DISTINCT user_id) AS c FROM login_history WHERE attempted_at >= CURDATE()"),
            'recent_logins'       => $db->query(
                'SELECT l.*, u.full_name FROM login_history l JOIN users u ON u.user_id = l.user_id ORDER BY l.attempted_at DESC LIMIT 10'
            )->fetchAll(),
        ]);
    }
}
