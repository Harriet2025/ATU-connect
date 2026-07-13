<?php
namespace Models;

use Core\BaseModel;

// =========================================================
// USER MODEL — base identity row for every role
// =========================================================
class User extends BaseModel
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'user_id';

    public static function findByIdentifierOrEmail(string $identifier): ?array
    {
        // Native (non-emulated) prepared statements can't reuse one named
        // placeholder twice, so identifier/email get separate bindings.
        $sql = 'SELECT u.*, r.role_name FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.identifier = :identifier OR u.email = :email
                LIMIT 1';
        $stmt = self::db()->prepare($sql);
        $stmt->execute(['identifier' => $identifier, 'email' => $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createUser(array $data): int
    {
        return self::create($data);
    }

    public static function findRoleIdByName(string $roleName): ?int
    {
        $stmt = self::db()->prepare('SELECT role_id FROM roles WHERE role_name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        $row = $stmt->fetch();
        return $row ? (int) $row['role_id'] : null;
    }

    // Lightweight directory search for the "New Message" recipient picker —
    // excludes the current user and returns only display-safe columns.
    public static function search(string $term, int $excludeUserId, ?string $role = null): array
    {
        $sql = 'SELECT u.user_id, u.full_name, u.identifier, r.role_name, d.department_name
                FROM users u
                JOIN roles r ON r.role_id = u.role_id
                LEFT JOIN departments d ON d.department_id = u.department_id
                WHERE u.user_id != :exclude_id
                  AND (u.full_name LIKE :term_name OR u.identifier LIKE :term_identifier)';
        $params = [
            'exclude_id'     => $excludeUserId,
            'term_name'      => '%' . $term . '%',
            'term_identifier'=> '%' . $term . '%',
        ];
        if ($role) {
            $sql .= ' AND r.role_name = :role';
            $params['role'] = $role;
        }
        $sql .= ' ORDER BY u.full_name LIMIT 20';

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
