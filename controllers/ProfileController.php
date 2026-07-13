<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Helpers\FileUploader;
use Models\User;
use Models\Student;
use Models\Lecturer;
use Models\Department;

// =========================================================
// PROFILE CONTROLLER — current user's profile + extension row
// =========================================================
class ProfileController extends BaseController
{
    public function show(Request $request): void
    {
        $this->requireAuth();

        $user = User::find(Auth::id());
        if (!$user) {
            Response::error('User not found.', HTTP_NOT_FOUND);
        }
        unset($user['password_hash']);

        // The UI displays Department and Academic Advisor as human-readable
        // names, but the base user/profile rows only carry FK ids. Self-
        // service editing of these two fields isn't supported, but resolving
        // them to display strings here is, so we do a couple of cheap
        // lookups rather than exposing raw ids to the frontend.
        if (!empty($user['department_id'])) {
            $dept = Department::find((int) $user['department_id']);
            $user['department_name'] = $dept['department_name'] ?? null;
        }

        if (Auth::role() === ROLE_STUDENT) {
            $user['profile'] = Student::findByUserId(Auth::id());
            if (!empty($user['profile']['academic_advisor_id'])) {
                $advisor = User::find((int) $user['profile']['academic_advisor_id']);
                $user['profile']['advisor_name'] = $advisor['full_name'] ?? null;
            }
        } elseif (Auth::role() === ROLE_LECTURER) {
            $user['profile'] = Lecturer::findByUserId(Auth::id());
        }

        Response::success($user);
    }

    public function update(Request $request): void
    {
        $this->requireAuth();

        $data = $this->validate($request->all(), [
            'full_name' => 'max:150',
            'phone'     => 'max:30',
            'email'     => 'email|max:150',
        ]);

        $email = $request->input('email');
        if ($email !== null && $email !== '') {
            if (Auth::role() === ROLE_STUDENT && !preg_match('/^[A-Za-z0-9]+@st\.atu\.edu\.gh$/', $email)) {
                Response::error('Validation failed.', HTTP_VALIDATION_ERROR, [
                    'email' => ['Student email must match the format 00934522B@st.atu.edu.gh'],
                ]);
            }
            $existing = User::findByEmail($email);
            if ($existing && (int) $existing['user_id'] !== Auth::id()) {
                Response::error('Validation failed.', HTTP_VALIDATION_ERROR, [
                    'email' => ['That email is already registered to another account.'],
                ]);
            }
        }

        $allowed = array_intersect_key($request->all(), array_flip([
            'full_name', 'phone', 'email', 'notify_email', 'notify_sms', 'notify_whatsapp',
        ]));
        foreach (['notify_email', 'notify_sms', 'notify_whatsapp'] as $flag) {
            if (array_key_exists($flag, $allowed)) {
                $allowed[$flag] = filter_var($allowed[$flag], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        if (!empty($allowed)) {
            User::update(Auth::id(), $allowed);
        }

        if (Auth::role() === ROLE_STUDENT) {
            $student = Student::findByUserId(Auth::id());
            $studentFields = array_intersect_key($request->all(), array_flip([
                'programme', 'level', 'emergency_contact_name', 'emergency_contact_phone',
            ]));
            if ($student && !empty($studentFields)) {
                Student::update((int) $student['student_id'], $studentFields);
            }
        } elseif (Auth::role() === ROLE_LECTURER) {
            $lecturer = Lecturer::findByUserId(Auth::id());
            $lecturerFields = array_intersect_key($request->all(), array_flip(['staff_title', 'office_location']));
            if ($lecturer && !empty($lecturerFields)) {
                Lecturer::update((int) $lecturer['lecturer_id'], $lecturerFields);
            }
        }

        $user = User::find(Auth::id());
        unset($user['password_hash']);
        Response::success($user, 'Profile updated.');
    }

    public function uploadPicture(Request $request): void
    {
        $this->requireAuth();

        $file = $request->file('picture');
        if (!$file) {
            Response::error('A picture file is required.', HTTP_VALIDATION_ERROR);
        }

        try {
            $path = FileUploader::upload($file, 'profile_pictures', APP_CONFIG['uploads']['allowed_image'], APP_CONFIG['uploads']['max_size_mb']);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), HTTP_VALIDATION_ERROR);
        }

        User::update(Auth::id(), ['profile_picture' => $path]);
        Response::success(['profile_picture' => $path], 'Profile picture updated.');
    }
}
