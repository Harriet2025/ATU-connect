<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Helpers\FileUploader;
use Models\Assignment;
use Models\AssignmentSubmission;
use Models\Course;
use Models\Student;
use Models\Notification;
use Models\CourseRegistration;
use Helpers\NotificationDispatcher;

// =========================================================
// ASSIGNMENT CONTROLLER — CRUD, submissions, grading
// =========================================================
class AssignmentController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $courseId = $request->query('course_id');
        $assignments = $courseId ? Assignment::forCourse((int) $courseId) : Assignment::all('due_date DESC');
        Response::success($assignments);
    }

    public function show(Request $request, $id): void
    {
        $this->requireAuth();

        $assignment = Assignment::find((int) $id);
        if (!$assignment) {
            Response::error('Assignment not found.', HTTP_NOT_FOUND);
        }
        Response::success($assignment);
    }

    public function store(Request $request): void
    {
        $this->requireRole([ROLE_LECTURER]);

        $data = $this->validate($request->all(), [
            'course_id' => 'required|numeric',
            'title'     => 'required|max:200',
            'due_date'  => 'required|date',
        ]);

        $briefFile = null;
        $file = $request->file('brief_file');
        if ($file) {
            try {
                $briefFile = FileUploader::upload($file, 'assignments', APP_CONFIG['uploads']['allowed_assignment'], APP_CONFIG['uploads']['max_size_mb']);
            } catch (\RuntimeException $e) {
                Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
            }
        }

        $id = Assignment::create([
            'course_id'   => $data['course_id'],
            'created_by'  => Auth::id(),
            'title'       => $data['title'],
            'description' => $request->input('description'),
            'brief_file'  => $briefFile,
            'max_score'   => $request->input('max_score', 100),
            'due_date'    => $data['due_date'],
        ]);

        // Alert every enrolled student that a new assignment was posted
        // (in-app + email/SMS per their prefs). The 24h "due soon" nudge is
        // handled separately by remindDueSoon() via the cron script.
        foreach (CourseRegistration::studentUserIdsForCourse((int) $data['course_id']) as $studentUserId) {
            NotificationDispatcher::notify(
                $studentUserId,
                'assignment_new',
                'New assignment: ' . $data['title'],
                'Due ' . $data['due_date'] . '. Open ATU Connect to view the details.',
                'medium',
                (int) $id
            );
        }

        Response::success(Assignment::find($id), 'Assignment created.', HTTP_CREATED);
    }

    public function update(Request $request, $id): void
    {
        $assignment = $this->authorizedAssignment((int) $id);

        $data = $request->all();
        $allowed = array_intersect_key($data, array_flip(['title', 'description', 'due_date', 'max_score']));
        if (empty($allowed)) {
            Response::error('No valid fields to update.', HTTP_VALIDATION_ERROR);
        }

        Assignment::update((int) $id, $allowed);
        Response::success(Assignment::find((int) $id), 'Assignment updated.');
    }

    public function destroy(Request $request, $id): void
    {
        $this->authorizedAssignment((int) $id);
        Assignment::delete((int) $id);
        Response::success(null, 'Assignment deleted.');
    }

    // Current student's own submission status for one assignment (or null if
    // not submitted yet) — not exposed by AssignmentSubmission::forAssignment()
    // (lecturer/admin-only, all students) so a small dedicated action is added
    // here, following the same shape as submit()/authorizedAssignment() above.
    public function mySubmission(Request $request, $id): void
    {
        $this->requireRole([ROLE_STUDENT]);

        if (!Assignment::find((int) $id)) {
            Response::error('Assignment not found.', HTTP_NOT_FOUND);
        }

        $student = Student::findByUserId(Auth::id());
        if (!$student) {
            Response::error('Student profile not found.', HTTP_FORBIDDEN);
        }

        Response::success(AssignmentSubmission::forStudent((int) $student['student_id'], (int) $id));
    }

    public function submissions(Request $request, $id): void
    {
        $this->requireRole([ROLE_LECTURER, ROLE_ADMIN]);

        if (!Assignment::find((int) $id)) {
            Response::error('Assignment not found.', HTTP_NOT_FOUND);
        }

        Response::success(AssignmentSubmission::forAssignment((int) $id));
    }

    public function submit(Request $request, $id): void
    {
        $this->requireRole([ROLE_STUDENT]);

        $assignment = Assignment::find((int) $id);
        if (!$assignment) {
            Response::error('Assignment not found.', HTTP_NOT_FOUND);
        }

        $student = Student::findByUserId(Auth::id());
        if (!$student) {
            Response::error('Student profile not found.', HTTP_FORBIDDEN);
        }

        $file = $request->file('file');
        if (!$file) {
            Response::error('A submission file is required.', HTTP_VALIDATION_ERROR);
        }

        try {
            $path = FileUploader::upload($file, 'assignments', APP_CONFIG['uploads']['allowed_assignment'], APP_CONFIG['uploads']['max_size_mb']);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
        }

        $isLate = strtotime('now') > strtotime($assignment['due_date']) ? 1 : 0;
        $existing = AssignmentSubmission::forStudent((int) $student['student_id'], (int) $id);

        if ($existing) {
            AssignmentSubmission::update((int) $existing['submission_id'], [
                'file_path'    => $path,
                'submitted_at' => date('Y-m-d H:i:s'),
                'is_late'      => $isLate,
                'grade'        => null,
                'feedback'     => null,
            ]);
            $submissionId = (int) $existing['submission_id'];
        } else {
            $submissionId = AssignmentSubmission::create([
                'assignment_id' => (int) $id,
                'student_id'    => (int) $student['student_id'],
                'file_path'     => $path,
                'is_late'       => $isLate,
            ]);
        }

        Response::success(AssignmentSubmission::find($submissionId), 'Submission recorded.', HTTP_CREATED);
    }

    public function grade(Request $request, $id): void
    {
        $this->requireRole([ROLE_LECTURER, ROLE_ADMIN]);

        $submission = AssignmentSubmission::find((int) $id);
        if (!$submission) {
            Response::error('Submission not found.', HTTP_NOT_FOUND);
        }

        $data = $this->validate($request->all(), ['grade' => 'required|numeric']);

        AssignmentSubmission::update((int) $id, [
            'grade'     => $data['grade'],
            'feedback'  => $request->input('feedback'),
            'graded_by' => Auth::id(),
            'graded_at' => date('Y-m-d H:i:s'),
        ]);

        $student = \Models\Student::find((int) $submission['student_id']);
        if ($student) {
            NotificationDispatcher::notify(
                (int) $student['user_id'],
                'assignment_graded',
                'Assignment graded',
                'Your submission has been graded.',
                'medium',
                (int) $submission['assignment_id']
            );
        }

        Response::success(AssignmentSubmission::find((int) $id), 'Submission graded.');
    }

    // Not wired to a route (no cron in plain PHP/XAMPP without an external
    // scheduler) — callable by a standalone script (see public/cron_reminders.php)
    // via Windows Task Scheduler or `php cron_reminders.php`.
    public static function remindDueSoon(): void
    {
        $sql = "SELECT a.assignment_id, a.title, a.due_date, cr.student_id, s.user_id
                FROM assignments a
                JOIN course_registrations cr ON cr.course_id = a.course_id AND cr.status = 'active'
                JOIN students s ON s.student_id = cr.student_id
                LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.assignment_id AND sub.student_id = cr.student_id
                WHERE sub.submission_id IS NULL
                  AND a.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)";
        $stmt = \Core\Database::connect()->prepare($sql);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            NotificationDispatcher::notify(
                (int) $row['user_id'],
                'assignment_due',
                'Assignment due soon',
                'Your "' . $row['title'] . '" submission is due on ' . $row['due_date'] . '.',
                'high',
                (int) $row['assignment_id']
            );
        }
    }

    private function authorizedAssignment(int $id): array
    {
        $this->requireAuth();
        $assignment = Assignment::find($id);
        if (!$assignment) {
            Response::error('Assignment not found.', HTTP_NOT_FOUND);
        }
        if ((int) $assignment['created_by'] !== Auth::id() && Auth::role() !== ROLE_ADMIN) {
            Response::error('Forbidden. You do not own this assignment.', HTTP_FORBIDDEN);
        }
        return $assignment;
    }
}
