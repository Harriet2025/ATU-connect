<?php
namespace Core;

// =========================================================
// BASE CONTROLLER — shared helpers for every controller
// =========================================================
abstract class BaseController
{
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Response::error('Unauthorized. Please log in.', HTTP_UNAUTHORIZED);
        }
    }

    protected function requireRole(array $roles): void
    {
        $this->requireAuth();
        if (!Auth::hasRole($roles)) {
            Response::error('Forbidden. You do not have access to this resource.', HTTP_FORBIDDEN);
        }
    }

    protected function validate(array $data, array $rules): array
    {
        $validator = new Validator();
        if (!$validator->validate($data, $rules)) {
            Response::error('Validation failed.', HTTP_VALIDATION_ERROR, $validator->errors());
        }
        return $data;
    }
}
