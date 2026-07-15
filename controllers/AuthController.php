<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Core\Database;
use Core\Session;
use Models\User;
use Models\Student;
use Models\Lecturer;

// =========================================================
// AUTH CONTROLLER — login, register, logout, current user
// =========================================================
class AuthController extends BaseController
{
    // Public, unauthenticated: the frontend fetches this once on load and
    // sends the value back as the X-CSRF-Token header on every non-GET call
    // (including login/register, which otherwise have no prior session token).
    public function csrfToken(Request $request): void
    {
        Response::success(['csrf_token' => Session::csrfToken()]);
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'identifier' => 'required',
            'password'   => 'required',
        ]);

        $user = Auth::attempt($data['identifier'], $data['password']);
        $existing = User::findByIdentifierOrEmail($data['identifier']);

        $this->logAttempt($existing['user_id'] ?? null, $user ? 'success' : 'failed');

        if (!$user) {
            Response::error('Invalid credentials.', HTTP_UNAUTHORIZED);
        }

        unset($user['password_hash']);
        Response::success($user, 'Logged in successfully.');
    }

    public function register(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'full_name' => 'required|max:150',
            'email'     => 'required|email|max:150',
            'identifier'=> 'required|max:50',
            'password'  => 'required|min:8',
            'role'      => 'required|in:' . implode(',', APP_CONFIG['roles']),
            'phone'     => 'required|min:9|max:30',
        ]);

        if ($data['role'] === ROLE_STUDENT && !preg_match('/^[A-Za-z0-9]+@st\.atu\.edu\.gh$/', $data['email'])) {
            Response::error('Validation failed.', HTTP_VALIDATION_ERROR, [
                'email' => ['Student email must match the format 00934522B@st.atu.edu.gh'],
            ]);
        }

        if ($data['role'] !== ROLE_STUDENT && !preg_match('/^[A-Za-z0-9._-]+@atu\.edu\.gh$/', $data['email'])) {
            Response::error('Validation failed.', HTTP_VALIDATION_ERROR, [
                'email' => ['Staff email must match the format name@atu.edu.gh (not a student @st.atu.edu.gh address)'],
            ]);
        }

        if (User::findByEmail($data['email'])) {
            Response::error('Email already registered.', HTTP_VALIDATION_ERROR);
        }

        $roleId = User::findRoleIdByName($data['role']);
        if (!$roleId) {
            Response::error('Invalid role.', HTTP_VALIDATION_ERROR);
        }

        $channels = $request->input('notify_channels', []);
        if (!is_array($channels)) {
            $channels = [];
        }
        $allowedChannels = ['email', 'sms', 'whatsapp'];
        if (array_diff($channels, $allowedChannels)) {
            Response::error('Validation failed.', HTTP_VALIDATION_ERROR, [
                'notify_channels' => ['notify_channels may only contain: ' . implode(', ', $allowedChannels)],
            ]);
        }

        // Lecturers must be vetted by their HOD before they're active on the
        // platform; every other role is usable immediately on sign-up.
        $approvalStatus = $data['role'] === ROLE_LECTURER ? 'pending' : 'approved';

        $userId = User::createUser([
            'full_name'       => $data['full_name'],
            'email'           => $data['email'],
            'identifier'      => $data['identifier'],
            'password_hash'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'role_id'         => $roleId,
            'department_id'   => $request->input('department_id'),
            'phone'           => $data['phone'],
            'approval_status' => $approvalStatus,
            'notify_email'    => empty($channels) || in_array('email', $channels, true) ? 1 : 0,
            'notify_sms'      => in_array('sms', $channels, true) ? 1 : 0,
            'notify_whatsapp' => in_array('whatsapp', $channels, true) ? 1 : 0,
        ]);

        if ($data['role'] === ROLE_STUDENT) {
            Student::create([
                'user_id'   => $userId,
                'programme' => $request->input('programme'),
                'level'     => $request->input('level'),
            ]);
        } elseif ($data['role'] === ROLE_LECTURER) {
            Lecturer::create([
                'user_id'         => $userId,
                'staff_title'     => $request->input('staff_title'),
                'office_location' => $request->input('office_location'),
            ]);
        }

        $user = User::find($userId);
        Auth::login(array_merge($user, ['role_name' => $data['role']]));
        unset($user['password_hash']);
        Response::success($user, 'Registration successful.', HTTP_CREATED);
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Response::success(null, 'Logged out.');
    }

    public function me(Request $request): void
    {
        $this->requireAuth();
        $user = User::find(Auth::id());
        if ($user) {
            unset($user['password_hash']);
        }
        Response::success($user);
    }

    private function logAttempt(?int $userId, string $status): void
    {
        if (!$userId) {
            return;
        }
        $stmt = Database::connect()->prepare(
            'INSERT INTO login_history (user_id, ip_address, user_agent, status) VALUES (:uid, :ip, :ua, :status)'
        );
        $stmt->execute([
            'uid'    => $userId,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'status' => $status,
        ]);
    }
}
