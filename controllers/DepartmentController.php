<?php
namespace Controllers;

use Core\BaseController;
use Core\Request;
use Core\Response;
use Models\Department;

// =========================================================
// DEPARTMENT CONTROLLER — public list for the registration
// dropdown (must be reachable before a user has an account).
// =========================================================
class DepartmentController extends BaseController
{
    // GET /api/departments — id + name only, alphabetical.
    public function index(Request $request): void
    {
        $rows = Department::all('department_name');
        $out = array_map(fn($d) => [
            'department_id'   => (int) $d['department_id'],
            'department_name' => $d['department_name'],
        ], $rows);
        Response::success($out);
    }
}
