<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\Course;
use Models\CourseRegistration;
use Models\Student;

// =========================================================
// COURSE CONTROLLER — listing, student registration/drop
// =========================================================
class CourseController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        Response::success(Course::feed());
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
