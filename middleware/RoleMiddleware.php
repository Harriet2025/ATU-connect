<?php
namespace Middleware;

use Core\Auth;
use Core\Response;

// =========================================================
// ROLE MIDDLEWARE — kept generic since Router instantiates middleware
// with no constructor args (it cannot receive an allowed-roles list).
// Fine-grained per-endpoint role checks are done in each controller
// method via BaseController::requireRole([...]) instead. This class
// only re-asserts that the user is authenticated, for symmetry.
// =========================================================
class RoleMiddleware
{
    protected array $allowedRoles = [];

    public function handle(): bool
    {
        if (!Auth::check()) {
            Response::error('Unauthorized. Please log in.', HTTP_UNAUTHORIZED);
            return false;
        }
        return true;
    }
}
