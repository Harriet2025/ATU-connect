<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\Notification;

// =========================================================
// NOTIFICATION CONTROLLER
// =========================================================
class NotificationController extends BaseController
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        Response::success(Notification::forUser(Auth::id()));
    }

    public function markRead(Request $request, $id): void
    {
        $this->requireAuth();

        $notification = Notification::find((int) $id);
        if (!$notification || (int) $notification['user_id'] !== Auth::id()) {
            Response::error('Notification not found.', HTTP_NOT_FOUND);
        }

        Notification::update((int) $id, ['is_read' => 1]);
        Response::success(null, 'Notification marked as read.');
    }

    public function markAllRead(Request $request): void
    {
        $this->requireAuth();
        Notification::markAllRead(Auth::id());
        Response::success(null, 'All notifications marked as read.');
    }

    public function destroy(Request $request, $id): void
    {
        $this->requireAuth();

        $notification = Notification::find((int) $id);
        if (!$notification || (int) $notification['user_id'] !== Auth::id()) {
            Response::error('Notification not found.', HTTP_NOT_FOUND);
        }

        Notification::delete((int) $id);
        Response::success(null, 'Notification deleted.');
    }
}
