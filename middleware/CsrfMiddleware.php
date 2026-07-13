<?php
namespace Middleware;

use Core\Session;
use Core\Response;

// =========================================================
// CSRF MIDDLEWARE — verifies X-CSRF-Token header on non-GET requests
// =========================================================
class CsrfMiddleware
{
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            return true;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::verifyCsrf($token)) {
            Response::error('Invalid CSRF token.', HTTP_FORBIDDEN);
            return false;
        }
        return true;
    }
}
