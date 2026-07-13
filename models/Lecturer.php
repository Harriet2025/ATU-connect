<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// LECTURER MODEL — extends users with staff profile fields
// =========================================================
class Lecturer extends BaseModel
{
    protected static string $table = 'lecturers';
    protected static string $primaryKey = 'lecturer_id';

    public static function findByUserId(int $userId): ?array
    {
        $rows = self::where(['user_id' => $userId]);
        return $rows[0] ?? null;
    }
}
