<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\Course;
use Models\CourseRegistration;
use Models\Student;
use Models\Lecturer;
use Models\User;
use Helpers\NotificationDispatcher;

// =========================================================
// COURSE CONTROLLER — listing, student registration/drop
// =========================================================
class CourseController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        $role = Auth::role();

        // Students get every course flagged with their own registration state
        // (the grid splits it into "My Courses" vs "Browse" client-side).
        if ($role === ROLE_STUDENT) {
            $student = Student::findByUserId(Auth::id());
            Response::success($student ? Course::feedForStudent((int) $student['student_id']) : []);
        }

        // A lecturer only ever sees the courses they teach — never the whole
        // catalogue.
        if ($role === ROLE_LECTURER) {
            $lecturer = Lecturer::findByUserId(Auth::id());
            Response::success($lecturer ? Course::feedForLecturer((int) $lecturer['lecturer_id']) : []);
        }

        // HOD / admin / other staff — full catalogue for oversight.
        Response::success(Course::feed());
    }

    // GET /api/courses/catalog — public (no auth): the semester course list
    // for the registration form's "courses you teach" picker, which runs
    // before the lecturer has a session.
    public function catalog(Request $request): void
    {
        Response::success(Course::catalog());
    }

    public function show(Request $request, $id): void
    {
        $this->requireAuth();
        $course = Course::find((int) $id);
        if (!$course) {
            Response::error('Course not found.', HTTP_NOT_FOUND);
        }
        Response::success($course);
    }

    // POST /api/courses — HOD (or admin) creates a course, optionally
    // assigning a lecturer at the same time.
    public function store(Request $request): void
    {
        $this->requireRole([ROLE_HOD, ROLE_ADMIN]);

        $data = $this->validate($request->all(), [
            'course_code' => 'required|max:20',
            'course_name' => 'required|max:150',
        ]);

        $lecturerId = $request->input('lecturer_id');
        try {
            $id = Course::create([
                'course_code' => $data['course_code'],
                'course_name' => $data['course_name'],
                'description' => $request->input('description'),
                'lecturer_id' => $lecturerId !== null && $lecturerId !== '' ? (int) $lecturerId : null,
            ]);
        } catch (\PDOException $e) {
            Response::error('A course with that code already exists.', HTTP_VALIDATION_ERROR);
        }

        Response::success(Course::find($id), 'Course created.', HTTP_CREATED);
    }

    // PUT /api/courses/{id} — HOD (or admin) edits a course or (re)assigns
    // its lecturer. Passing lecturer_id = "" clears the assignment.
    public function update(Request $request, $id): void
    {
        $this->requireRole([ROLE_HOD, ROLE_ADMIN]);

        $course = Course::find((int) $id);
        if (!$course) {
            Response::error('Course not found.', HTTP_NOT_FOUND);
        }

        $allowed = array_intersect_key($request->all(), array_flip([
            'course_code', 'course_name', 'description', 'lecturer_id',
        ]));
        if (array_key_exists('lecturer_id', $allowed)) {
            $allowed['lecturer_id'] = $allowed['lecturer_id'] !== '' ? (int) $allowed['lecturer_id'] : null;
        }
        if (empty($allowed)) {
            Response::error('No valid fields to update.', HTTP_VALIDATION_ERROR);
        }

        Course::update((int) $id, $allowed);
        Response::success(Course::find((int) $id), 'Course updated.');
    }

    // GET /api/courses/mine — the logged-in lecturer's own courses
    // (for the "message my class" course picker).
    public function mine(Request $request): void
    {
        $this->requireRole([ROLE_LECTURER]);
        $lecturer = Lecturer::findByUserId(Auth::id());
        if (!$lecturer) {
            Response::success([]);
        }
        Response::success(Course::forLecturer((int) $lecturer['lecturer_id']));
    }

    // POST /api/courses/{id}/message-class — lecturer broadcasts a general
    // message to every student enrolled on one of their courses. Fans out
    // through NotificationDispatcher, so each student gets it in-app and by
    // email/SMS according to their own notification preferences.
    public function messageClass(Request $request, $id): void
    {
        $this->requireRole([ROLE_LECTURER]);

        $data = $this->validate($request->all(), [
            'message' => 'required|max:650',
        ]);

        $course = Course::find((int) $id);
        if (!$course) {
            Response::error('Course not found.', HTTP_NOT_FOUND);
        }

        // Ownership: the course's lecturer_id must map to this user.
        $lecturer = Lecturer::findByUserId(Auth::id());
        if (!$lecturer || (int) $course['lecturer_id'] !== (int) $lecturer['lecturer_id']) {
            Response::error('You can only message a class you teach.', HTTP_FORBIDDEN);
        }

        $me = User::find(Auth::id());
        $title = 'Message from ' . ($me['full_name'] ?? 'your lecturer') . ' · ' . $course['course_code'];
        $recipients = CourseRegistration::studentUserIdsForCourse((int) $id);
        foreach ($recipients as $studentUserId) {
            NotificationDispatcher::notify(
                $studentUserId,
                'class_message',
                $title,
                $data['message'],
                'medium',
                (int) $id
            );
        }

        Response::success(['recipients' => count($recipients)], 'Message sent to ' . count($recipients) . ' student(s).');
    }

    public function register(Request $request, $id): void
    {
        $this->requireRole([ROLE_STUDENT]);

        $course = Course::find((int) $id);
        if (!$course) {
            Response::error('Course not found.', HTTP_NOT_FOUND);
        }

        $student = Student::findByUserId(Auth::id());
        if (!$student) {
            Response::error('Student profile not found.', HTTP_FORBIDDEN);
        }

        $existing = CourseRegistration::findByStudentAndCourse((int) $student['student_id'], (int) $id);
        if ($existing) {
            if ($existing['status'] !== 'active') {
                CourseRegistration::update((int) $existing['registration_id'], ['status' => 'active']);
            }
            Response::success(null, 'Already registered for this course.');
        }

        try {
            CourseRegistration::create([
                'student_id' => $student['student_id'],
                'course_id'  => $id,
            ]);
        } catch (\PDOException $e) {
            Response::error('Could not register for course (already registered).', HTTP_VALIDATION_ERROR);
        }

        Response::success(null, 'Registered for course.', HTTP_CREATED);
    }

    public function drop(Request $request, $id): void
    {
        $this->requireRole([ROLE_STUDENT]);

        $student = Student::findByUserId(Auth::id());
        if (!$student) {
            Response::error('Student profile not found.', HTTP_FORBIDDEN);
        }

        $existing = CourseRegistration::findByStudentAndCourse((int) $student['student_id'], (int) $id);
        if (!$existing) {
            Response::error('You are not registered for this course.', HTTP_NOT_FOUND);
        }

        CourseRegistration::update((int) $existing['registration_id'], ['status' => 'dropped']);
        Response::success(null, 'Dropped course.');
    }
}
