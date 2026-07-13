<?php

require_once __DIR__ . '/Model.php';

class ClassGroup extends Model
{
    /** All classes with a human-readable label, for registration dropdowns. */
    public static function allForSelect(): array
    {
        $stmt = self::db()->query(
            "SELECT c.id, CONCAT(p.name, ' — ', l.name, ' (', c.intake_year, ')') AS label
             FROM classes c
             JOIN programmes p ON p.id = c.programme_id
             JOIN levels l ON l.id = c.level_id
             ORDER BY p.name, l.name"
        );
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT c.*, p.name AS programme_name, p.department_id, l.name AS level_name
             FROM classes c
             JOIN programmes p ON p.id = c.programme_id
             JOIN levels l ON l.id = c.level_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
