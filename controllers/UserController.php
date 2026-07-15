<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Core\Auth;
use Models\User;

// =========================================================
// USER CONTROLLER — directory search (message recipient picker)
// =========================================================
class UserController extends BaseController
{
    public function search(Request $request): void
    {
        $this->requireAuth();

        $term = trim((string) $request->query('search', ''));
        if ($term === '') {
            Response::success([]);
        }

        Response::success(User::search($term, Auth::id(), $request->query('role')));
    }

    // GET /api/users — full directory (every other active account) for the
    // "new message" picker, so any user can start a private, request-gated
    // conversation with anyone in the system.
    public function directory(Request $request): void
    {
        $this->requireAuth();
        Response::success(User::directory(Auth::id()));
    }
}
