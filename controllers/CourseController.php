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
