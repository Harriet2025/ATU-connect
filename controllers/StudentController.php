<?php

require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../core/FileUpload.php';
require_once __DIR__ . '/../core/Notifier.php';

class StudentController
{
    private function classId(): ?int
    {
        $stmt = Database::getInstance()->prepare('SELECT class_id, department_id FROM profiles WHERE user_id = ?');
        $stmt->execute([Auth::id()]);
        $profile = $stmt->fetch();
        return $profile ? (int)$profile['class_id'] : null;
    }

    public function dashboard(): void
    {
        Auth::requireRole('student');
        $pageTitle = 'Student Dashboard';
        $activeNav = 'dashboard';
        $classId = $this->classId();

        $courses = $classId ? Course::forClass($classId) : [];
        $courseCount = count($courses);
        $assignments = $classId ? Assignment::forStudent(Auth::id(), $classId, 5) : [];
        $deadlines = $classId ? Assignment::upcomingDeadlines($classId, 5) : [];
        $announcements = Announcement::forUser(Auth::id(), null, $classId, 5);

        $pendingCount = count(array_filter($assignments, fn($a) => empty($a['submission_id'])));

        include __DIR__ . '/../views/student/dashboard.php';
    }

    public function courses(): void
    {
        Auth::requireRole('student');
        $pageTitle = 'My Courses';
        $activeNav = 'courses';
        $classId = $this->classId();
        $courses = $classId ? Course::forClass($classId) : [];
        include __DIR__ . '/../views/student/courses.php';
    }

    public function assignments(): void
    {
        Auth::requireRole('student');
        $pageTitle = 'Assignments';
        $activeNav = 'assignments';
        $classId = $this->classId();
        $assignments = $classId ? Assignment::forStudent(Auth::id(), $classId, 100) : [];
        include __DIR__ . '/../views/student/assignments.php';
    }

    public function submitAssignment(int $assignmentId): void
    {
        Auth::requireRole('student');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Your session expired. Please try again.');
            header('Location: ' . BASE_URL . '/student/assignments');
            exit;
        }

        $assignment = Assignment::findById($assignmentId);
        $classId = $this->classId();

        if (!$assignment || !$classId || (int)$assignment['class_id'] !== $classId) {
            http_response_code(403);
            echo 'You are not permitted to submit to this assignment.';
            return;
        }

        if (empty($_FILES['submission_file']) || $_FILES['submission_file']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Please choose a file to submit.');
            header('Location: ' . BASE_URL . '/student/assignments');
            exit;
        }

        $upload = FileUpload::store($_FILES['submission_file'], 'submissions/' . $assignmentId . '/' . Auth::id());

        if (!$upload['success']) {
            Session::flash('error', $upload['error']);
            header('Location: ' . BASE_URL . '/student/assignments');
            exit;
        }

        $isLate = strtotime($assignment['deadline']) < time();
        Submission::create($assignmentId, Auth::id(), $upload['path'], $isLate);

        Session::flash('success', 'Assignment submitted successfully' . ($isLate ? ' (marked late).' : '.'));
        header('Location: ' . BASE_URL . '/student/assignments');
        exit;
    }

    public function profile(): void
    {
        Auth::requireRole('student');
        $pageTitle = 'My Profile';
        $activeNav = '';
        $user = Auth::user();
        $errors = [];
        include __DIR__ . '/../views/student/profile.php';
    }

    public function updateProfile(): void
    {
        Auth::requireRole('student');

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            Session::flash('error', 'Your session expired. Please try again.');
            header('Location: ' . BASE_URL . '/student/profile');
            exit;
        }

        $v = new Validator($_POST);
        $v->required('full_name', 'Full name');

        if ($v->fails()) {
            $errors = $v->errors();
            $user = Auth::user();
            include __DIR__ . '/../views/student/profile.php';
            return;
        }

        $fields = [
            'full_name' => Validator::clean($_POST['full_name']),
            'phone'     => Validator::clean($_POST['phone'] ?? ''),
        ];

        if (!empty($_FILES['profile_photo']['name'])) {
            $upload = FileUpload::store($_FILES['profile_photo'], 'profile_photos');
            if ($upload['success']) {
                $fields['profile_photo'] = $upload['path'];
            }
        }

        User::updateProfile(Auth::id(), $fields);

        if (!empty($_POST['password'])) {
            $v2 = new Validator($_POST);
            $v2->minLength('password', 8, 'Password')->matches('password_confirm', 'password', 'Password confirmation');
            if (!$v2->fails()) {
                User::updatePassword(Auth::id(), $_POST['password']);
            }
        }

        Session::flash('success', 'Profile updated successfully.');
        header('Location: ' . BASE_URL . '/student/profile');
        exit;
    }
}
