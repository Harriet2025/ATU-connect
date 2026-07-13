<?php
namespace Middleware;

use Core\Auth;
use Core\Response;

// =========================================================
// AUTH MIDDLEWARE — blocks any route requiring a logged-in session
// =========================================================
class AuthMiddleware
{
    public function handle(): bool
    {
        if (!Auth::check()) {
            Response::error('Unauthorized. Please log in.', HTTP_UNAUTHORIZED);
            return false;
        }
        return true;
    }
}
