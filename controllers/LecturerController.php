<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Models\User;
use Models\Lecturer;
use Helpers\NotificationDispatcher;

// =========================================================
// LECTURER CONTROLLER — HOD approval workflow + roster
// =========================================================
class LecturerController extends BaseController
{
    // Roles allowed to vet/manage lecturers. HOD is the primary owner of
    // this workflow; administrator is included so the platform is never
    // stuck if a department has no active HOD.
    private const MANAGER_ROLES = [ROLE_HOD, ROLE_ADMIN];

    // GET /api/lecturers/pending — accounts awaiting approval.
    public function pending(Request $request): void
    {
        $this->requireRole(self::MANAGER_ROLES);
        Response::success(User::pendingLecturers());
    }

    // GET /api/lecturers — approved lecturers (for the assign dropdown).
    public function index(Request $request): void
    {
        $this->requireRole(self::MANAGER_ROLES);
        Response::success(Lecturer::approvedList());
    }

    // POST /api/lecturers/{userId}/approve
    public function approve(Request $request, $userId): void
    {
        $this->requireRole(self::MANAGER_ROLES);
        $user = $this->fetchPendingLecturer((int) $userId);

        User::setApprovalStatus((int) $userId, 'approved');
        NotificationDispatcher::notify(
            (int) $userId,
            'account',
            'Your lecturer account is approved',
            'You can now be assigned courses and post to your students on ATU Connect.',
            'high'
        );
        Response::success(null, 'Lecturer approved.');
    }

    // POST /api/lecturers/{userId}/reject
    public function reject(Request $request, $userId): void
    {
        $this->requireRole(self::MANAGER_ROLES);
        $this->fetchPendingLecturer((int) $userId);

        User::setApprovalStatus((int) $userId, 'rejected');
        NotificationDispatcher::notify(
            (int) $userId,
            'account',
            'Your lecturer account was not approved',
            'Please contact your department for more information.',
            'high'
        );
        Response::success(null, 'Lecturer rejected.');
    }

    private function fetchPendingLecturer(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            Response::error('User not found.', HTTP_NOT_FOUND);
        }
        return $user;
    }
}
