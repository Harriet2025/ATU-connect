<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\Section;
use Models\SectionMessage;
use Models\User;
use Models\Department;
use Models\Lecturer;
use Models\Student;
use Models\Course;
use Models\CourseRegistration;
use Helpers\NotificationDispatcher;

// =========================================================
// SECTION CONTROLLER — group message channels
//
// Scopes:
//   course     — a class channel: the lecturer + enrolled students. Every
//                member can post (discussion), so the whole class sees and
//                can respond.
//   department — everyone in a department reads; staff/executives post.
//   school     — everyone reads; only institution-level executives post.
// Membership is virtual (derived from scope) — no members table.
// =========================================================
class SectionController extends BaseController
{
    // GET /api/sections — every channel this user can see, each flagged
    // with whether they're allowed to post in it.
    public function index(Request $request): void
    {
        $this->requireAuth();
        $ctx = $this->ctx();
        $out = [];

        // Whole-school channel (everyone).
        $out[] = $this->decorate(Section::ensure('school', null, 'Whole School', $ctx['userId']), $ctx);

        // The user's department channel.
        if ($ctx['departmentId']) {
            $dept = Department::find($ctx['departmentId']);
            if ($dept) {
                $s = Section::ensure('department', $ctx['departmentId'], $dept['department_name'] . ' Department', $ctx['userId']);
                $out[] = $this->decorate($s, $ctx);
            }
        }

        // A class channel for each course the user teaches or is enrolled on.
        foreach ($ctx['courses'] as $cid => $info) {
            $c = $info['course'];
            $s = Section::ensure('course', (int) $cid, $c['course_code'] . ' — ' . $c['course_name'], $ctx['userId']);
            $out[] = $this->decorate($s, $ctx);
        }

        Response::success($out);
    }

    // GET /api/sections/{id}/messages
    public function messages(Request $request, $id): void
    {
        $this->requireAuth();
        $ctx = $this->ctx();
        $section = Section::find((int) $id);
        if (!$section) {
            Response::error('Section not found.', HTTP_NOT_FOUND);
        }
        if (!$this->canView($section, $ctx)) {
            Response::error('You do not have access to this section.', HTTP_FORBIDDEN);
        }

        Response::success([
            'section'  => [
                'section_id' => (int) $section['section_id'],
                'name'       => $section['name'],
                'scope_type' => $section['scope_type'],
                'can_post'   => $this->canPost($section, $ctx),
            ],
            'messages' => SectionMessage::forSection((int) $id),
        ]);
    }

    // POST /api/sections/{id}/messages
    public function postMessage(Request $request, $id): void
    {
        $this->requireAuth();
        $ctx = $this->ctx();
        $section = Section::find((int) $id);
        if (!$section) {
            Response::error('Section not found.', HTTP_NOT_FOUND);
        }
        if (!$this->canPost($section, $ctx)) {
            Response::error('You are not allowed to post in this section.', HTTP_FORBIDDEN);
        }

        $data = $this->validate($request->all(), ['body' => 'required|max:2000']);

        $messageId = SectionMessage::create([
            'section_id' => (int) $id,
            'sender_id'  => $ctx['userId'],
            'body'       => $data['body'],
        ]);

        $this->notifyMembers($section, $ctx, $data['body']);

        Response::success(['message_id' => $messageId], 'Message posted.', HTTP_CREATED);
    }

    // ---------- permission + context helpers ----------

    private function ctx(): array
    {
        $userId = Auth::id();
        $user = User::find($userId);
        $lecturer = Lecturer::findByUserId($userId);
        $student = Student::findByUserId($userId);

        $ctx = [
            'userId'       => $userId,
            'role'         => Auth::role(),
            'departmentId' => !empty($user['department_id']) ? (int) $user['department_id'] : null,
            'lecturerId'   => $lecturer['lecturer_id'] ?? null,
            'studentId'    => $student['student_id'] ?? null,
        ];
        // course_id => ['role' => 'lecturer'|'student', 'course' => row]
        $courses = [];
        if ($ctx['lecturerId']) {
            foreach (Course::forLecturer((int) $ctx['lecturerId']) as $c) {
                $courses[(int) $c['course_id']] = ['role' => 'lecturer', 'course' => $c];
            }
        }
        if ($ctx['studentId']) {
            foreach (Course::forStudent((int) $ctx['studentId']) as $c) {
                if (!isset($courses[(int) $c['course_id']])) {
                    $courses[(int) $c['course_id']] = ['role' => 'student', 'course' => $c];
                }
            }
        }
        $ctx['courses'] = $courses;
        return $ctx;
    }

    private function canView(array $section, array $ctx): bool
    {
        switch ($section['scope_type']) {
            case 'school':     return true;
            case 'department': return $ctx['role'] === ROLE_ADMIN || (int) $section['scope_id'] === $ctx['departmentId'];
            case 'course':     return $ctx['role'] === ROLE_ADMIN || isset($ctx['courses'][(int) $section['scope_id']]);
        }
        return false;
    }

    private function canPost(array $section, array $ctx): bool
    {
        switch ($section['scope_type']) {
            case 'school':
                return in_array($ctx['role'], SCHOOL_BROADCAST_ROLES, true);
            case 'department':
                if ($ctx['role'] === ROLE_ADMIN) return true;
                return in_array($ctx['role'], DEPARTMENT_BROADCAST_ROLES, true)
                    && (int) $section['scope_id'] === $ctx['departmentId'];
            case 'course':
                // Every class member (lecturer + enrolled students) may post.
                return $ctx['role'] === ROLE_ADMIN || isset($ctx['courses'][(int) $section['scope_id']]);
        }
        return false;
    }

    private function decorate(array $section, array $ctx): array
    {
        $last = Section::lastMessage((int) $section['section_id']);
        return [
            'section_id'      => (int) $section['section_id'],
            'name'            => $section['name'],
            'scope_type'      => $section['scope_type'],
            'can_post'        => $this->canPost($section, $ctx),
            'last_message'    => $last['body'] ?? null,
            'last_sender'     => $last['sender_name'] ?? null,
            'last_message_at' => $last['created_at'] ?? null,
        ];
    }

    // In-app + per-channel notification fan-out. Bounded scopes (course,
    // department) notify their members; the school channel is left as a
    // read-in-place feed to avoid an unbounded blast to every account.
    private function notifyMembers(array $section, array $ctx, string $body): void
    {
        $recipients = [];
        if ($section['scope_type'] === 'course') {
            $recipients = CourseRegistration::studentUserIdsForCourse((int) $section['scope_id']);
            // include the course lecturer so a student's post reaches them too
            $course = Course::find((int) $section['scope_id']);
            if ($course && !empty($course['lecturer_id'])) {
                $lec = Lecturer::find((int) $course['lecturer_id']);
                if ($lec) $recipients[] = (int) $lec['user_id'];
            }
        } elseif ($section['scope_type'] === 'department') {
            $recipients = Student::studentUserIdsInDepartment((int) $section['scope_id']);
        }

        $preview = mb_substr($body, 0, 160);
        foreach (array_unique($recipients) as $uid) {
            if ((int) $uid === (int) $ctx['userId']) continue;
            NotificationDispatcher::notify(
                (int) $uid,
                'section_message',
                'New post in ' . $section['name'],
                $preview,
                'low',
                (int) $section['section_id']
            );
        }
    }
}
