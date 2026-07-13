<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// DEPARTMENT MODEL
// =========================================================
class Department extends BaseModel
{
    protected static string $table = 'departments';
    protected static string $primaryKey = 'department_id';
}
